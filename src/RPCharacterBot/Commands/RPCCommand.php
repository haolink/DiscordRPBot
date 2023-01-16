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
     * Message the webhook received upon return.
     *
     * @var Message
     */
    private $webhookReturnMessage;

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
            $content = $this->messageLine;

            $files = array();
    
            if (is_array($message->attachments) && count($message->attachments) > 0 && !$this->ignoreAttachments) {                
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

        $data = array(
            'username' => $character->getCharacterName()
        );

        $avatar = $character->getCharacterAvatar();

        if (!is_null($avatar)) {
            if (strpos($avatar, '://') === false) {
                $avatar = $this->bot->getConfig('avatar_url') . $avatar;
            }

            $data['avatar_url'] = $avatar;
        } 

        if (count($files) > 0) {
            $data['embeds'] = $files;
        } elseif (empty($content))         {
            return null;
        }

        $data['content'] = $content ?? 'Empty';

        $deferred = new Deferred();
        
        $options = array('wait' => true);
        if ($this->messageInfo->isRPThread) {
            $options['thread_id'] = $this->messageInfo->thread->id;
        }        

        $that = $this;
        $this->messageInfo->webhook->execute($data, $options)->then(function (Message $returnMessage) use ($deferred, $that) {
            $that->webhookReturnMessage = $returnMessage;
            $deferred->resolve();            
        });

        return $deferred->promise();
    }

    /**
     * Are RP replacements available here?
     *
     * @return boolean
     */
    protected function isRPAvailableInChannel() {
        if ($this->messageInfo->channel->getUseSubThreads()) {
            return $this->messageInfo->isRPThread;
        } else {
            return is_null($this->messageInfo->thread);
        }
    }

    /**
     * Receives the result of the webhook.
     *
     * @return Message|null
     */
    public function getWebhookReturnMessage() : ?Message {
        return $this->webhookReturnMessage;
    }
}