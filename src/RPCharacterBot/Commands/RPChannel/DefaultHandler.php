<?php

namespace RPCharacterBot\Commands\RPChannel;

use Discord\Parts\Thread\Thread;
use React\Promise\Deferred;
use RPCharacterBot\Commands\RPCCommand;
use RPCharacterBot\Common\MessageInfo;
use React\Promise\ExtendedPromiseInterface;
use RPCharacterBot\Common\MessageCache;

class DefaultHandler extends RPCCommand
{
    /**
     * Default handler can be directly initiated.
     *
     * @param MessageInfo $messageInfo
     */
    public function __construct(MessageInfo $messageInfo)
    {
        parent::__construct($messageInfo);
    }

    /**
     * Replaces a user message.
     *
     * @return ExtendedPromiseInterface|null
     */
    protected function handleCommandInternal(): ?ExtendedPromiseInterface
    {
        $info = $this->messageInfo;

        $message = $info->message;

        if (is_null($info->webhook)) {
            return null;
        }        

        if ($this->isRPAvailableInChannel()) {
            if (is_null($this->messageInfo->currentCharacter)) {
                $deferred = new Deferred();
    
                $that = $this;
                $message->delete()->then(function() use($deferred, $that) {
                    $that->replyDM('You don\'t have a character set up. ' . PHP_EOL . PHP_EOL .
                        'Please use `new [shortcut] [Character name]` in these DMs to set on up.');
                    $deferred->resolve();
                });
    
                return $deferred->promise();                        
            }
    
            return $this->resubmitMessageAsCharacter($message, $this->messageInfo->currentCharacter);
        }

        if ($info->channel->getUseSubThreads()) {
            $info->preventDeletion = true;

            $deferred = new Deferred();

            $duration = 1440;
            if ($message->guild->feature_seven_day_thread_archive) {
                $duration = 10080;
            } elseif ($message->guild->feature_three_day_thread_archive) {
                $duration =  4320;
            }
            
            $content = $info->message->content;
            if (strlen($content) > 30) {
                $this->reply('This thread title is too long.')->then(function() use ($deferred) {
                    $deferred->resolve();
                });

                return $deferred->promise();
            }

            $info->message->startThread($content, $duration)->then(function(Thread $thread) use ($deferred) {
                $deferred->resolve();
            });

            return $deferred->promise();
        }        
    }
}
