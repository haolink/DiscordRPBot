<?php

namespace RPCharacterBot\Commands\Guild;

use RPCharacterBot\Commands\GuildCommand;
use React\Promise\ExtendedPromiseInterface;

class OocCommand extends GuildCommand
{    
    /**
     * User executing this command requires the following permissions.
     *
     * @var array
     */
    protected static $REQUIRED_USER_PERMISSIONS = array('ADMINISTRATOR');
    
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

        $words = $this->getMessageWords();

        if(count($words) < 1) {
            return $this->replyDM('Usage: ' . $this->messageInfo->mainPrefix . 'ooc 0/1');
        }

        $allowOoc = $this->readBooleanString($words[0]);
        
        if(is_null($allowOoc)) {
            return $this->replyDM('Usage: ' . $this->messageInfo->mainPrefix . 'ooc 0/1');
        }

        $currentSetting = $this->messageInfo->channel->getAllowOoc();

        $newState = $allowOoc ? 'enabled':'disabled';
        if ($currentSetting == $allowOoc) {
            return $this->replyDM('OOC talk in this channel has already been ' . $newState . '.');
        } else {
            $this->messageInfo->channel->setAllowOoc($allowOoc);
            return $this->sendSelfDeletingReply('OOC talk in this channel is now ' . $newState . '.');
        }        
    }
}
