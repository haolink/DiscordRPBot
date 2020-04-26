<?php

namespace RPCharacterBot\Commands\DM;

use React\Promise\ExtendedPromiseInterface;
use RPCharacterBot\Commands\DMCommand;

class AvatarCommand extends DMCommand
{
    /**
     * Command to set a character's avatar.
     *
     * @return ExtendedPromiseInterface|null
     */
    protected function handleCommandInternal(): ?ExtendedPromiseInterface
    {
        $words = $this->getMessageWords();

        if(count($words) < 2) {
            return $this->replyDM('Usage: avatar [shortcut] [avatar url]');
        }

        $shortCut = strtolower($words[0]);                
        $existingCharacter = $this->getCharacterByShortcut($shortCut);

        if (is_null($existingCharacter)) {
            return $this->replyDM('A character with the shortcut ' . $shortCut . ' doesn\'t exist.');
        }

        $existingCharacter->setCharacterAvatar($words[1]);

        return $this->replyDM('The profile picture for ' . $existingCharacter->getCharacterName() . ' has been set!');
    }
}
