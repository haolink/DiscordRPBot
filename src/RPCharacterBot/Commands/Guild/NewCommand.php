<?php

namespace RPCharacterBot\Commands\Guild;

use Discord\Parts\Thread\Thread;
use React\Promise\Deferred;
use RPCharacterBot\Commands\GuildCommand;
use React\Promise\ExtendedPromiseInterface;
use RPCharacterBot\Common\Helpers\ThreadHelper;

class NewCommand extends GuildCommand
{    
    /**
     * Sets up whether OOC talk is allowed or not in a chnnel.
     *
     * @return ExtendedPromiseInterface|null
     */
    protected function handleCommandInternal(): ?ExtendedPromiseInterface
    {
        if (!$this->messageInfo->isRPChannel) {
            return $this->replyDM('This channel is not an RP channel!');
        }

        if (!$this->messageInfo->channel->getUseSubThreads()) {
            return $this->replyDM('This channel is not used for threaded RPs!');
        }

        $words = $this->getMessageWords();

        if(count($words) < 1) {
            return $this->replyDM('Usage: ' . $this->messageInfo->mainPrefix . 'new [Thread name]');
        }

        $threadName = implode(' ', $words);

        $info = $this->messageInfo;
        $message = $info->message;

        $info->preventDeletion = true;

        $deferred = new Deferred();

        $duration = 1440;
        if ($message->guild->feature_seven_day_thread_archive) {
            $duration = 10080;
        } elseif ($message->guild->feature_three_day_thread_archive) {
            $duration =  4320;
        }
        
        if (strlen($threadName) > 30) {
            $this->reply('This thread title is too long.')->then(function() use ($deferred) {
                $deferred->resolve();
            });

            return $deferred->promise();
        }

        $info->message->startThread($threadName, $duration)->then(function(Thread $thread) use ($info, $deferred) {
            $deferred->resolve();
        });

        return $deferred->promise();
    }
}
