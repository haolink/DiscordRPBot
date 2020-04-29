<?php

namespace RPCharacterBot\Commands\DM;

use RPCharacterBot\Commands\DMCommand;
use React\Promise\ExtendedPromiseInterface;
use RPCharacterBot\Model\Character;

class NickCommand extends DMCommand
{    
    /**
     * Command to change the name of a character.
     *
     * @return ExtendedPromiseInterface|null
     */
    protected function handleCommandInternal(): ?ExtendedPromiseInterface
    {
        $words = $this->getMessageWords();

        if(count($words) < 2) {
            return $this->replyDM('Usage: n [shortcut] [Character name]');
        }

        $shortCut = strtolower($words[0]);
        $existingCharacter = $this->getCharacterByShortcut($shortCut);

        if (is_null($existingCharacter)) {
            return $this->replyDM('You don\'t have a character with the shortcut ' . $shortCut . '.');
        }

        unset($words[0]);
        $fullName = implode(' ', $words);
        $errorMessage = $this->sanitiseName($fullName);

        if (!is_null($errorMessage)) {
            return $this->replyDM($errorMessage);
        }                

        $existingCharacter->setCharacterName($fullName);

        return $this->replyDM('You\'ve successfully renamed this character to  ' . $fullName . '.');
    }
}
