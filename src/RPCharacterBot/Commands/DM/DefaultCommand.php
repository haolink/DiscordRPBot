<?php

namespace RPCharacterBot\Commands\DM;

use RPCharacterBot\Commands\DMCommand;
use React\Promise\ExtendedPromiseInterface;

class DefaultCommand extends DMCommand
{
    /**
     * Sets the default character.
     *
     * @return ExtendedPromiseInterface|null
     */
    protected function handleCommandInternal(): ?ExtendedPromiseInterface
    {
        $words = $this->getMessageWords();

        if(count($words) < 1) {
            return $this->replyDM('Usage: default [shortcut]');
        }

        $shortCut = strtolower($words[0]);                
        $existingCharacter = $this->getCharacterByShortcut($shortCut);

        if (is_null($existingCharacter)) {
            return $this->replyDM('A character with the shortcut ' . $shortCut . ' doesn\'t exist.');
        }

        $currentDefault = null;

        foreach ($this->messageInfo->characters as $character) {
            if ($character->getDefaultCharacter()) {
                $currentDefault = $character;
                break;
            }
        }

        if ($character == $currentDefault) {
            return $this->replyDM($character->getCharacterName() . ' is your default character.');
        }

        if (!is_null($currentDefault)) {
            $currentDefault->setDefaultCharacter(false);
        }

        $existingCharacter->setDefaultCharacter(true);

        return $this->replyDM($character->getCharacterName() . ' is now your default character.');
    }
}
