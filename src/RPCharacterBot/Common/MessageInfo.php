<?php

namespace RPCharacterBot\Common;

use CharlotteDunois\Collect\Collection;
use CharlotteDunois\Yasmin\Interfaces\DMChannelInterface;
use Discord\Parts\Channel\Message;
use Discord\Parts\Channel\Webhook;
use Discord\Parts\Channel\Channel as TextChannel;
use Discord\Parts\Thread\Thread;
use Discord\Repository\Channel\WebhookRepository;
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
use RPCharacterBot\Model\ThreadUser;

/**
 * Descriptor for a final RP Bot message to be parsed.
 * 
 * @property Message $message Original Discord message object.
 * @property Message[]|null $lastSubmittedMessages Last submitted messages by this user in this channel.
 * @property Guild $guild Discord RP guild data for this message.
 * @property Channel|null $channel RP channel info.
 * @property Thread|null $thread Discord thread.
 * @property Webhook $webhook Discord Webhook object.
 * @property User $user RP user object.
 * @property Character[] $characters RP characters of the user.
 * @property Character|null $currentCharacter RP character to use in this environment.
 * @property Character|null $formerCharacter Former RP character to use in this environment.
 * @property CharacterDefaultModel|null $characterDefaultSettings Default settings for this channel/guild.
 * @property bool $isDM Are we currently having DM talk.
 * @property bool $isRPChannel Are we currently in a RP channel.
 * @property bool $isRPThread Are we currently in a RP thread.
 * @property string $oocSequence OOC Text by this user.
 * @property string $mainPrefix Main prefix to be used in this guild.
 * @property string $quickPrefix Quick prefix to be used in this guild.q
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
     * Last submitted user messages.
     *
     * @var Message
     */    
    protected $_lastSubmittedMessages;

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
     * Is the message coming from a thread?
     *
     * @var boolean
     */
    protected $_isThread;

    /**
     * Is this an RP thread?
     *
     * @var boolean
     */
    protected $_isRPThread;

    /**
     * Thread.
     *
     * @var Thread|null
     */
    protected $_thread;

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
     * Gets the former character for the back command.
     *
     * @var Character|null
     */
    protected $_formerCharacter;

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
     * Prevent deleting of a message.
     *
     * @var boolean
     */
    public $preventDeletion;

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

        $this->preventDeletion = false;
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
        if ($this->_message->channel->type == TextChannel::TYPE_DM) {
            $this->_isRPChannel = false;
            $this->_isDM = true;
            $this->_isThread = false;
            $this->_mainPrefix = null;
            $this->_quickPrefix = null;
            $this->fetchUserData($deferred);
            return;
        }

        $that = $this;
        Guild::fetchSingleByQuery(array('id' => $this->_message->channel->guild->id))->then(
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

        $channel = $this->_message->channel;
        
        if ($channel instanceof Thread) {
            $this->_isThread = true;
            $this->_thread = $channel;
            $channel = $channel->parent;            
        } else {
            $this->_isThread = false;
        }

        $that = $this;
        Channel::fetchSingleByQuery(array(
            'id' => $channel->id,
            'guild_id' => $this->_guild->getId()
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
        if (!is_null($this->thread)) {
            $discordChannel = $this->thread->parent;
        } else {
            $discordChannel = $this->_message->channel;
        }
        
        if (!($discordChannel instanceof TextChannel)) {
            $this->fetchUserData($deferred);
            return;
        }

        $that = $this;
        /** @var TextChannel $discChannel */
        $discordChannel->webhooks->freshen()->then(
            function (WebhookRepository $webhooks) use ($that, $discordChannel, $channel, $deferred) {
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
        $this->_isRPThread = ($this->_isThread && $this->_isRPChannel && !is_null($this->_channel) && $this->_channel->getUseSubThreads());

        $that = $this;
        User::fetchSingleByQuery(array(
            'id' => $this->_message->author->id,
        ))->then(
            function (User $user) use ($that, $deferred) {
                $that->_user = $user;

                $that->_oocSequence = $that->_user->getOocPrefix() ?? $that->_bot->getConfig('oocPrefix', '//');
                $that->fetchCachedMessages($deferred);
            }
        );
    }

    /**
     * Checks if there is a cached message for this user.
     *
     * @param Deferred $deferred
     * @return void
     */
    private function fetchCachedMessages(Deferred $deferred)
    {
        if (!is_null($this->_user) && !is_null($this->_channel)) {
            if ($this->isRPThread) {
                $this->_lastSubmittedMessages = 
                    MessageCache::findLastUserMessages($this->_user->getId(), $this->_thread->id);
            } else {
                $this->_lastSubmittedMessages = 
                    MessageCache::findLastUserMessages($this->_user->getId(), $this->_channel->getId());
            }            
        } else {
            $this->_lastSubmittedMessages = null;
        }

        $this->fetchCharacterData($deferred);
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
                $this->fetchDefaultCharacterForChannel($deferred);
                break;
            case Guild::RPCHAR_SETTING_THREAD:
            default:
                if ($this->_isRPThread) {
                    $this->fetchDefaultCharacterForThread($deferred);
                } else {
                    $this->fetchDefaultCharacterForChannel($deferred);
                }                
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
     * Fetches the default character for the current channel.
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
     * Fetches the default character for the current thread.
     *
     * @param Deferred $deferred
     * @return void
     */
    private function fetchDefaultCharacterForThread(Deferred $deferred)
    {
        if(is_null($this->_thread)) {
            $this->resolveDeferred($deferred);
            return;
        }

        $that = $this;
        ThreadUser::fetchSingleByQuery(array(            
            'user_id' => $this->_user->getId(), 
            'thread_id' => $this->_thread->id
        ))->then(
                function (ThreadUser $threadUser) use ($that, $deferred) {
                    $that->_characterDefaultSettings = $threadUser;
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
        $formerSelectedGuildCharacterId = $this->_characterDefaultSettings->getFormerCharacterId();
        $selectedGuildCharacterId = $this->_characterDefaultSettings->getDefaultCharacterId();

        $this->_formerCharacter = null;

        if(!is_null($formerSelectedGuildCharacterId) && $formerSelectedGuildCharacterId == $selectedGuildCharacterId) {
            $this->_characterDefaultSettings->setFormerCharacterId(null);
        }

        if (!is_null($formerSelectedGuildCharacterId)) {
            /** @var Character|null $foundFormerCharacter */
            $foundFormerCharacter = null;
            foreach ($this->_characters as $character) {
                /** @var Character $character */
                if ($character->getId() == $formerSelectedGuildCharacterId) {
                    $foundFormerCharacter = $character;
                    break;
                }
            }

            if (is_null($foundFormerCharacter)) {
                $this->_characterDefaultSettings->setFormerCharacterId(null);
            } else {
                $this->_formerCharacter = $foundFormerCharacter;                
            }
        }        

        if (is_null($this->_currentCharacter)) {
            $this->_characterDefaultSettings->setDefaultCharacterId(null);
            $this->_characterDefaultSettings->setFormerCharacterId(null);
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
                $defaultCharacterId = $this->_currentCharacter->getId();
                if ($defaultCharacterId == $formerSelectedGuildCharacterId) {
                    $this->_characterDefaultSettings->setFormerCharacterId(null);
                    $this->_formerCharacter = null;
                }
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
