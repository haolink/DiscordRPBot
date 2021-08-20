<?php

namespace RPCharacterBot\Commands\Guild;

use Discord\Parts\Channel\Webhook;
use Discord\Parts\Channel\Channel as DiscordChannel;
use Discord\Parts\Thread\Thread;
use React\Promise\Deferred;
use RPCharacterBot\Commands\GuildCommand;
use React\Promise\ExtendedPromiseInterface;
use RPCharacterBot\Common\Helpers\ThreadHelper;
use RPCharacterBot\Model\Channel;

class SetupCommand extends GuildCommand
{
    /**
     * Bot needs to have the following permissions in the channel.
     *
     * @var array
     */
    protected static $REQUIRED_CHANNEL_PERMISSIONS = array('manage_webhooks', 'manage_messages');

    /**
     * Depending on parameters the bot might require this permission.
     *
     * @var array
     */
    protected static $OPTIONAL_CHANNEL_PERMISSIONS = array('use_public_threads');

    /**
     * User executing this command requires the following permissions.
     *
     * @var array
     */
    protected static $REQUIRED_USER_PERMISSIONS = array('administrator');
    
    /**
     * Sets up a channel for RPing.
     *
     * @return ExtendedPromiseInterface|null
     */
    protected function handleCommandInternal(): ?ExtendedPromiseInterface
    {
        if ($this->messageInfo->isRPChannel) {
            return $this->replyDM('This channel already is an RP channel!');            
        }

        if ($this->messageInfo->message->channel instanceof Thread) {
            return $this->replyDM('This command cannot be used in a thread!');
        }

        $words = $this->getMessageWords();

        if(count($words) < 1) {
            return $this->sendSelfDeletingReply('Usage: ' . $this->messageInfo->mainPrefix . 'setup [channel/thread]');
        }

        $setupWay = mb_strtolower($words[0]);

        if (!in_array($setupWay, array('channel', 'channels', 'thread', 'threads'))) {
            return $this->sendSelfDeletingReply('Usage: ' . $this->messageInfo->mainPrefix . 'setup [channel/thread]');
        }

        $useSubThreads = false;
        
        switch ($setupWay) {
            case 'thread':
            case 'threads':
                $useSubThreads = true;
                break;
            case 'channel':
            case 'channels':
            default:
                $useSubThreads = false;
                break;
        }

        if ($useSubThreads) {
            if (!$this->getOptionalPermissionAvailable('use_public_threads')) {
                return $this->replyDM('To set this up for threaded RPs, you must give the permission use_public_threads to the bot!');
            }
        }

        $deferred = new Deferred();
        $that = $this;

        /** @var DiscordChannel $textChannel */
        $textChannel = $this->messageInfo->message->channel;

        /** @var WebHook $webhook */
        $webhook = $textChannel->webhooks->create(array(
            'name' => 'RPBot',
            'guild_id' => $this->messageInfo->message->channel->guild->id,
            'channel_id' => $this->messageInfo->message->channel->id
        ));

        $inputMessage = $that->messageInfo->message;
        $textChannel->webhooks->save($webhook)->then(function(Webhook $webhook) use ($that, $deferred, $textChannel, $inputMessage, $useSubThreads) {
            $channel = Channel::registerRpChannel($textChannel->id, $webhook->id, $that->messageInfo->guild->getId(), true, $useSubThreads);
            
            if ($useSubThreads) {
                $that->reply('The channel has been registered for RPing using threads! To create a thread please use the following command:' . PHP_EOL . '`' . $this->messageInfo->mainPrefix . 'new [Thread name]`')->then(function() use($deferred, $that, $inputMessage) {
                    ThreadHelper::onThreadedRpChannelCreated($inputMessage->channel, $that->bot->getClient());
                    $inputMessage->delete()->done();
                    $deferred->resolve();
                });
            } else {
                $that->sendSelfDeletingReply('The channel has been registered for RPing!')->then(function() use($deferred, $that, $inputMessage) {
                    $inputMessage->delete()->done();
                    $deferred->resolve();
                });
            }

        }, function($error) use($that, $deferred) {
            $that->reply('Something went wrong here!')->then(function() use($deferred) {
                $deferred->resolve();
            });
        });

        return $deferred->promise();
    }
}
