<?php

namespace RPCharacterBot\Commands\RPChannel;

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
}
