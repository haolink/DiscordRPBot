<?php

namespace RPCharacterBot\Commands\DM;

use RPCharacterBot\Commands\DMCommand;
use React\Promise\ExtendedPromiseInterface;

class OocCommand extends DMCommand
{    
    /**
     * Command to change the OOC string of a player.
     *
     * @return ExtendedPromiseInterface|null
     */
    protected function handleCommandInternal(): ?ExtendedPromiseInterface
    {
        $words = $this->getMessageWords();

        if (count($words) < 1) {
            return $this->replyDM('Usage: ooc [ooc string]');
        }

        if (count($words) > 2) {
            return $this->replyDM('Spaces are not allowed!');
        }

        $oocTag = $words[0];
        if (mb_strlen($oocTag) > 5) {
            return $this->replyDM('The OOC tag may only be up to 5 characters long!');
        }

        $this->messageInfo->user->setOocPrefix($oocTag);

        return $this->replyDM('You\'ve changed your personal OOC tag to "' . $oocTag . '".');
    }
}
