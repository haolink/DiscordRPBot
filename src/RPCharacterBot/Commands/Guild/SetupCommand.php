<?php

namespace RPCharacterBot\Commands\Guild;

use Discord\Parts\Channel\Webhook;
use Discord\Parts\Channel\Channel as DiscordChannel;
use Discord\Parts\Thread\Thread;
use React\Promise\Deferred;
use RPCharacterBot\Commands\GuildCommand;
use React\Promise\ExtendedPromiseInterface;
use RPCharacterBot\Model\Channel;

class SetupCommand extends GuildCommand
{
    /**
     * Bot needs to have the following permissions in the channel.
     *
     * @var array
     */
    protected static $REQUIRED_CHANNEL_PERMISSIONS = array('manage_webhooks', 'manage_messages', 'use_public_threads');

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
        $textChannel->webhooks->save($webhook)->then(function(Webhook $webhook) use ($that, $deferred, $textChannel, $inputMessage) {
            $channel = Channel::registerRpChannel($textChannel->id, $webhook->id, $that->messageInfo->guild->getId());
            $that->sendSelfDeletingReply('The channel has been registered for RPing!')->then(function() use($deferred, $that, $inputMessage) {
                $inputMessage->delete()->done();
                $deferred->resolve();
            });
        }, function($error) use($that, $deferred) {
            $that->reply('Something went wrong here!')->then(function() use($deferred) {
                $deferred->resolve();
            });
        });

        return $deferred->promise();
    }
}
