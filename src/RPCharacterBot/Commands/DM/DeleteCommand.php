<?php

namespace RPCharacterBot\Commands\DM;

use React\Promise\ExtendedPromiseInterface;
use RPCharacterBot\Commands\DMCommand;

class DeleteCommand extends DMCommand
{
    /**
     * Command to delete a character.
     *
     * @return ExtendedPromiseInterface|null
     */
    protected function handleCommandInternal(): ?ExtendedPromiseInterface
    {
        $words = $this->getMessageWords();

        if(count($words) < 1) {
            return $this->replyDM('Usage: delete [shortcut]');
        }

        $shortCut = strtolower($words[0]);                
        $existingCharacter = $this->getCharacterByShortcut($shortCut);

        if (is_null($existingCharacter)) {
            return $this->replyDM('A character with the shortcut ' . $shortCut . ' doesn\'t exist.');
        }

        $existingCharacter->delete();

        return $this->replyDM($existingCharacter->getCharacterName() . ' has been deleted!');
    }
}
