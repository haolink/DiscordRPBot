<?php

namespace RPCharacterBot\Commands;

use Discord\Parts\Channel\Message;
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
    
            if (is_array($message->attachments) && count($message->attachments) > 0) {                
                foreach ($message->attachments as $attachment) {
                    if (substr($attachment->content_type, 0, 5) != 'image') {
                        continue;
                    }
                    $files[] = array(
                        'url' => $attachment->url,
                        'type' => 'rich',
                        'image' => [
                            'url' => $attachment->url,
                            'width' => $attachment->width,
                            'height' => $attachment->height
                        ]
                    );
                }
            }
        }        

        if (empty($content)) {
            $content = '';
        }

        $options = array(
            'username' => $character->getCharacterName()
        );

        $avatar = $character->getCharacterAvatar();

        if (!is_null($avatar)) {
            if (strpos($avatar, '://') === false) {
                $avatar = $this->bot->getConfig('avatar_url') . $avatar;
            }

            $options['avatar_url'] = $avatar;
        } 

        if (count($files) > 0) {
            $options['embeds'] = $files;
        } elseif (empty($content))         {
            return null;
        }

        $options['content'] = $content ?? 'Empty';

        MessageCache::submitToWebHook($this->messageInfo->user->getId(), $this->messageInfo->channel->getId(),
            $character->getCharacterName(), $content);

        $deferred = new Deferred();
        
        $this->messageInfo->webhook->execute($options)->then(function () use ($message, $deferred) {
            //$message->delete()->then(function () use($deferred) {
                $deferred->resolve();
            //});
        });

        return $deferred->promise();
    }
}