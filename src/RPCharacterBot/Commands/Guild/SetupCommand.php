<?php

namespace RPCharacterBot\Commands\Guild;

use CharlotteDunois\Yasmin\Models\Webhook;
use CharlotteDunois\Yasmin\Models\TextChannel;
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
    protected static $REQUIRED_CHANNEL_PERMISSIONS = array('MANAGE_WEBHOOKS', 'MANAGE_MESSAGES');

    /**
     * User executing this command requires the following permissions.
     *
     * @var array
     */
    protected static $REQUIRED_USER_PERMISSIONS = array('ADMINISTRATOR');
    
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

        $deferred = new Deferred();
        $that = $this;

        /** @var TextChannel $textChannel */
        $textChannel = $this->messageInfo->message->channel;
        $textChannel->createWebhook('RPBot')->then(function(Webhook $webhook) use ($that, $deferred, $textChannel) {
            $channel = Channel::registerRpChannel($textChannel->id, $webhook->id, $that->messageInfo->guild->getId());
            $that->replyDM('The channel has been registered for RPing!')->then(function() use($deferred) {
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
