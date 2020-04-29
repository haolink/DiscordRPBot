<?php

namespace RPCharacterBot\Commands;

use CharlotteDunois\Yasmin\Models\Message;
use React\Promise\Deferred;
use React\Promise\ExtendedPromiseInterface;
use RPCharacterBot\Common\CommandHandler;
use RPCharacterBot\Common\MessageCache;
use RPCharacterBot\Model\Character;

abstract class RPCCommand extends CommandHandler
{
    protected static $COMMAND_NAMESPACE = 'RPCharacterBot\\Commands\\RPChannel';

    /**
     * Resubmits a message including attachments as a new character.
     *
     * @param Message $message
     * @param Character $character
     * @return ExtendedPromiseInterface|null
     */    
    protected function resubmitMessageAsCharacter(Message $message, Character $character) : ?ExtendedPromiseInterface
    {
        $content = $message->content;

        if (empty($message->content)) {
            $content = '';
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
            'username' => $character->getCharacterName(),
            'avatar' => $character->getCharacterAvatar()
        );

        if (count($files) > 0) {
            $options['files'] = $files;
        }

        MessageCache::submitToWebHook($this->messageInfo->user->getId(), $this->messageInfo->channel->getId(),
            $character->getCharacterName(), $content);

        $deferred = new Deferred();

        $this->messageInfo->webhook->send($content, $options)->then(function () use ($message, $deferred) {
            $message->delete()->then(function () use($deferred) {
                $deferred->resolve();
            });
        });

        return $deferred->promise();
    }
}