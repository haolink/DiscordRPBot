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
     * @param string|null $content
     * @return ExtendedPromiseInterface|null
     */    
    protected function resubmitMessageAsCharacter($message, Character $character, ?string $content = null) : ?ExtendedPromiseInterface
    {
        if (!is_null($content)) {
            $files = array();
        } elseif ($message instanceof Message) {
            $content = $message->content;

            $files = array();
    
            if (is_object($message->attachments) && $message->attachments->count() > 0) {
                foreach ($message->attachments as $attachment) {
                    $files[] = array(
                        'name' => $attachment->filename,
                        'path' => $attachment->url
                    );
                }
            }
        }        

        if (empty($content)) {
            $content = '';
        }

        $options = array(
            'username' => $character->getCharacterName(),
            'avatar' => $character->getCharacterAvatar()
        );

        if (count($files) > 0) {
            $options['files'] = $files;
        } elseif (empty($content))         {
            return null;
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