<?php

namespace RPCharacterBot\Commands\Guild;

use Discord\Parts\Channel\Webhook;
use Discord\Parts\Channel\Channel;
use Discord\Parts\Thread\Thread;
use React\Promise\Deferred;
use RPCharacterBot\Commands\GuildCommand;
use React\Promise\ExtendedPromiseInterface;

class UnsetCommand extends GuildCommand
{
    /**
     * Bot needs to have the following permissions in the channel.
     *
     * @var array
     */
    protected static $REQUIRED_CHANNEL_PERMISSIONS = array('manage_webhooks');

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
        if (!$this->messageInfo->isRPChannel) {
            return $this->replyDM('This channel is not an RP channel!');
        }

        if ($this->messageInfo->message->channel instanceof Thread) {
            return $this->replyDM('This command cannot be used in a thread!');
        }

        $deferred = new Deferred();
        $that = $this;

        $this->messageInfo->channel->delete();
        $this->messageInfo->message->channel->webhooks->delete($this->messageInfo->webhook)->then(function() use($that) {
            $that->messageInfo->message->delete()->done();
            $that->sendSelfDeletingReply('Channel has been unregistered!');
        });

        return $deferred->promise();
    }
}
