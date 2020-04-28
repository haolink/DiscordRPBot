<?php

namespace RPCharacterBot\Commands\Guild;

use CharlotteDunois\Yasmin\Models\Webhook;
use CharlotteDunois\Yasmin\Models\TextChannel;
use React\Promise\Deferred;
use RPCharacterBot\Commands\GuildCommand;
use React\Promise\ExtendedPromiseInterface;
use RPCharacterBot\Model\Channel;

class UnsetCommand extends GuildCommand
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
        if (!$this->messageInfo->isRPChannel) {
            return $this->replyDM('This channel is not an RP channel!');
        }

        $deferred = new Deferred();
        $that = $this;

        $this->messageInfo->channel->delete();
        $this->messageInfo->webhook->delete()->then(function() use($that) {
            $that->reply('Channel has been unregistered!');
        });

        return $deferred->promise();
    }
}
