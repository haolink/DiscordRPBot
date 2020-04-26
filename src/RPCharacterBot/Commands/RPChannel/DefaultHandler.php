<?php

namespace RPCharacterBot\Commands\RPChannel;

use React\Promise\Deferred;
use RPCharacterBot\Commands\RPCCommand;
use RPCharacterBot\Common\MessageInfo;
use React\Promise\ExtendedPromiseInterface;

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
        $content = $message->content;

        if (is_null($info->webhook)) {
            return null;
        }        

        if (empty($message->content)) {
            $content = '';
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

        $files = array();

        if (is_object($message->attachments) && $message->attachments->count() > 0) {
            foreach ($message->attachments as $attachment) {
                $files[] = array(
                    'name' => $attachment->filename,
                    'path' => $attachment->url
                );
            }
        }

        $options = array(
            'username' => $info->currentCharacter->getCharacterName(),
            'avatar' => $info->currentCharacter->getCharacterAvatar()
        );

        if (count($files) > 0) {
            $options['files'] = $files;
        }

        $deferred = new Deferred();

        $info->webhook->send($content, $options)->then(function () use ($message, $deferred) {
            $message->delete()->then(function () use($deferred) {
                $deferred->resolve();
            });
        });

        return $deferred->promise();
    }
}
