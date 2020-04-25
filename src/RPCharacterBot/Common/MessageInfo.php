<?php

namespace RPCharacterBot\Common;

use CharlotteDunois\Collect\Collection;
use CharlotteDunois\Yasmin\Interfaces\DMChannelInterface;
use CharlotteDunois\Yasmin\Models\Message;
use CharlotteDunois\Yasmin\Models\TextChannel;
use CharlotteDunois\Yasmin\Models\Webhook;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use RPCharacterBot\Bot\Bot;
use RPCharacterBot\Exception\BotException;
use RPCharacterBot\Model\Channel;
use RPCharacterBot\Model\Character;
use RPCharacterBot\Model\Guild;
use RPCharacterBot\Model\User;
use RPCharacterBot\Common\CharacterDefaultModel;
use RPCharacterBot\Model\ChannelUser;
use RPCharacterBot\Model\GuildUser;

/**
 * Descriptor for a final RP Bot message to be parsed.
 * 
 * @property Message $message Original Discord message object.
 * @property Guild $guild Discord RP guild data for this message.
 * @property Channel|null $channel RP channel info.
 * @property Webhook $webhook Discord Webhook object.
 * @property User $user RP user object.
 * @property Character[] $characters RP characters of the user.
 * @property Character|null $currentCharacter RP character to use in this environment.
 * @property CharacterDefaultModel|null $characterDefaultSettings Default settings for this channel/guild.
 * @property bool $isDM Are we currently having DM talk.
 * @property bool $isRPChannel Are we currently in a RP channel.
 * @property string $oocSequence OOC Text by this user.
 * @property string $mainPrefix Main prefix to be used in this guild.
 * @property string $quickPrefix Quick prefix to be used in this guild.
 * @property Bot $bot Discord main bot object.
 */
class MessageInfo
{
    /**
     * Discord message object.
     *
     * @var Message
     */
    protected $_message;

    /**
     * RP Guild data.
     *
     * @var Guild
     */
    protected $_guild;

    /**
     * RP channel data.
     *
     * @var Channel|null
     */
    protected $_channel;

    /**
     * Webhook.
     *
     * @var Webhook
     */
    protected $_webhook;

    /**
     * RP user data.
     *
     * @var User
     */
    protected $_user;

    /**
     * User characters.
     *
     * @var Character[]
     */
    protected $_characters;

    /**
     * Gets the current character.
     *
     * @var Character|null
     */
    protected $_currentCharacter;

    /**
     * Describer for the default character setting of this guild/channel.
     *
     * @var CharacterDefaultModel|null
     */
    protected $_characterDefaultSettings;

    /**
     * Is this a DM message?
     *
     * @var bool
     */
    protected $_isDM;

    /**
     * Is this an RP channel?
     *
     * @var bool
     */
    protected $_isRPChannel;

    /**
     * The user's OOC sequence to listen to.
     *
     * @var string
     */
    protected $_oocSequence;

    /**
     * The guild's main command prefix.
     *
     * @var string
     */
    protected $_mainPrefix;

    /**
     * The guild's quick command prefix.
     *
     * @var string
     */
    protected $_quickPrefix;

    /**
     * Main object of the Discord bot.
     *
     * @var Bot
     */
    protected $_bot;

    /**
     * Magic method for simple properties.
     *
     * @param string $key
     * @return void
     */
    public function __get($key)
    {
        if (property_exists($this, '_' . $key)) {
            $propName = '_' . $key;
            return $this->{$propName};
        } else {
            throw new BotException('Invalid property ' . $key);
        }
    }

    /**
     * Creates a simple bot message object.
     *
     * @param Bot $bot
     * @param Message $message
     */
    private function __construct(Bot $bot, Message $message)
    {
        $this->_bot = $bot;
        $this->_message = $message;
    }

    /**
     * Will run the submitted message through all necessary message parsers.
     *
     * @param Bot $bot
     * @param Message $message
     * @return PromiseInterface
     */
    public static function parseMessage(Bot $bot, Message $message): PromiseInterface
    {
        $mi = new MessageInfo($bot, $message);
        return $mi->parseMessageInternal();
    }

    /**
     * Will run the submitted message through all necessary message parsers.
     *
     * @return PromiseInterface
     */
    private function parseMessageInternal(): PromiseInterface
    {
        $deferred = new Deferred();

        $that = $this;
        $this->_bot->getLoop()->futureTick(function () use ($that, $deferred) {
            $that->fetchGuildData($deferred);
        });

        return $deferred->promise();
    }

    /**
     * Fetches the guild data.
     *
     * @param Deferred $deferred
     * @return void
     */
    private function fetchGuildData(Deferred $deferred)
    {
        if ($this->_message->channel instanceof DMChannelInterface) {
            $this->_isRPChannel = false;
            $this->_isDM = true;
            $this->_mainPrefix = null;
            $this->_quickPrefix = null;
            $this->fetchUserData($deferred);
            return;
        }

        $that = $this;
        Guild::fetchSingleByQuery(array('id' => $this->_message->guild->id))->then(
            function (Guild $guild) use ($that, $deferred) {
                $that->_guild = $guild;
                $that->_isDM = false;

                $that->_mainPrefix = $that->_guild->getMainPrefix() ?? $that->_bot->getConfig('mainCommandPrefix', 'rp!');
                $that->_quickPrefix = $that->_guild->getQuickPrefix() ?? $that->_bot->getConfig('quickCommandPrefix', '..');

                $that->fetchChannelData($deferred);
            }
        );
    }

    /**
     * Fetches data about the current channel.
     *
     * @param Deferred $deferred
     * @return void
     */
    private function fetchChannelData(Deferred $deferred)
    {
        $this->_webhook = null;
        $this->_isRPChannel = false;
        $this->_channel = null;

        $that = $this;
        Channel::fetchSingleByQuery(array(
            'id' => $this->_message->channel->id,
            'server_id' => $this->_guild->getId()
        ), false)->then(
            function (?Channel $channel) use ($that, $deferred) {
                if (!is_null($channel)) { //Channel found
                    if (!is_null($channel->getWebhook())) { //Channel has a registered webhook
                        $that->_webhook = $channel->getWebhook();
                        $that->_channel = $channel;
                        $that->_isRPChannel = true;

                        $that->fetchUserData($deferred);
                    } else { //Channel webhook isn't available
                        $that->fetchChannelWebhookData($deferred, $channel);
                    }
                } else {
                    $that->fetchUserData($deferred);
                }
            }
        );
    }

    /**
     * Checks if the channel webhook is still available.
     *
     * @param Deferred $deferred
     * @param Channel $channel
     * 
     * @return void
     */
    private function fetchChannelWebhookData(Deferred $deferred, Channel $channel)
    {
        $discordChannel = $this->_message->channel;
        if (!($discordChannel instanceof TextChannel)) {
            $this->fetchUserData($deferred);
            return;
        }

        $that = $this;
        /** @var TextChannel $discChannel */
        $discordChannel->fetchWebhooks()->then(
            function (Collection $webhooks) use ($that, $discordChannel, $channel, $deferred) {
                $matchedWebhook = null;
                foreach ($webhooks as $webhook) {
                    /** @var Webhook $webhook */
                    if ($webhook->id == $channel->getWebhookId()) {
                        $matchedWebhook = $webhook;
                    }
                }

                if (is_null($matchedWebhook)) {
                    $channel->delete();
                    $that->fetchUserData($deferred);
                } else {
                    $channel->setWebhook($matchedWebhook);

                    $that->_webhook = $matchedWebhook;
                    $that->_channel = $channel;
                    $that->_isRPChannel = true;

                    $that->fetchUserData($deferred);
                }
            }
        );
    }

    /**
     * Fetches data relevant to the current user.
     *
     * @param Deferred $deferred
     * @return void
     */
    private function fetchUserData(Deferred $deferred)
    {
        $that = $this;
        User::fetchSingleByQuery(array(
            'id' => $this->_message->author->id,
        ))->then(
            function (User $user) use ($that, $deferred) {
                $that->_user = $user;

                $that->_oocSequence = $that->_user->getOocPrefix() ?? $that->_bot->getConfig('oocPrefix', '//');
                $that->fetchCharacterData($deferred);
            }
        );
    }

    /**
     * Queries the RP characters of the logged in user.
     *
     * @param Deferred $deferred
     * @return void
     */
    private function fetchCharacterData(Deferred $deferred)
    {
        $that = $this;
        Character::fetchByQuery(array(
            'user_id' => $this->_user->getId()
        ), false)->then(function (array $characters) use ($that, $deferred) {
            $that->_characters = $characters;
            $that->fetchDefaultCharacter($deferred);
        });
    }

    /**
     * Fetches the selected character.
     *
     * @param Deferred $deferred
     * @return void
     */
    private function fetchDefaultCharacter(Deferred $deferred)
    {
        $default = null;
        if (count($this->_characters) > 0) {
            $firstCharacter = $this->_characters[0];
            foreach ($this->_characters as $character) {
                if ($character->getDefaultCharacter()) {
                    $default = $character;
                    break;
                }
            }
            if (is_null($default)) {
                $default = $firstCharacter;
            }
        }
        $this->_currentCharacter = $default;

        if($this->_isDM) {
            $this->resolveDeferred($deferred);
            return;
        }

        switch ($this->_guild->getRpCharacterSetting()) {
            case Guild::RPCHAR_SETTING_GUILD:
                $this->fetchDefaultCharacterForGuild($deferred);
                break;
            case Guild::RPCHAR_SETTING_CHANNEL:
            default:
                $this->fetchDefaultCharacterForChannel($deferred);
                break;
        }
    }

    /**
     * Fetches the default character for the selected guild.
     *
     * @param Deferred $deferred
     * @return void
     */
    private function fetchDefaultCharacterForGuild(Deferred $deferred)
    {
        $that = $this;
        GuildUser::fetchSingleByQuery(array(
            'user_id' => $this->_user->getId(), 
            'guild_id' => $this->_guild->getId()
        ))->then(
                function (GuildUser $guildUser) use ($that, $deferred) {
                    $that->_characterDefaultSettings = $guildUser;
                    $that->fetchSelectedCharacter($deferred);
                }
            );
    }

    /**
     * Fetches the default character for the selected guild.
     *
     * @param Deferred $deferred
     * @return void
     */
    private function fetchDefaultCharacterForChannel(Deferred $deferred)
    {
        if(is_null($this->_channel)) {
            $this->resolveDeferred($deferred);
            return;
        }

        $that = $this;
        ChannelUser::fetchSingleByQuery(array(            
            'user_id' => $this->_user->getId(), 
            'channel_id' => $this->_channel->getId()
        ))->then(
                function (ChannelUser $channelUser) use ($that, $deferred) {
                    $that->_characterDefaultSettings = $channelUser;
                    $that->fetchSelectedCharacter($deferred);
                }
            );
    }

    /**
     * Fetches the currently selected character for the current guild/channel.
     *
     * @param Deferred $deferred
     * @return void
     */
    private function fetchSelectedCharacter(Deferred $deferred)
    {
        $selectedGuildCharacterId = $this->_characterDefaultSettings->getDefaultCharacterId();

        if (is_null($this->_currentCharacter)) {
            $this->_characterDefaultSettings->setDefaultCharacterId(null);
            $this->resolveDeferred($deferred);
            return;
        }

        if ($selectedGuildCharacterId != $this->_currentCharacter->getId()) {
            /** @var Character|null $foundCharacter */
            $foundCharacter = null;
            foreach ($this->_characters as $character) {
                /** @var Character $character */
                if ($character->getId() == $selectedGuildCharacterId) {
                    $foundCharacter = $character;
                    break;
                }
            }

            if (is_null($foundCharacter)) {
                //Reset to default character
                $this->_characterDefaultSettings->setDefaultCharacterId($this->_currentCharacter->getId());
            } else {
                //Found the character for this guild/channel.
                $this->_currentCharacter = $foundCharacter;
            }
        }
        
        $this->resolveDeferred($deferred);
    }

    /**
     * Resolve Promise.
     *
     * @param Deferred $deferred
     * @return void
     */
    private function resolveDeferred(Deferred $deferred) {
        $deferred->resolve($this);
    }    
}
