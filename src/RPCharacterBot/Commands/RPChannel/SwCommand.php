<?php

namespace RPCharacterBot\Commands\RPChannel;

use RPCharacterBot\Commands\RPCCommand;
use React\Promise\ExtendedPromiseInterface;

class SwCommand extends RPCCommand
{
    protected function handleCommandInternal(): ?ExtendedPromiseInterface
    {
        $words = $this->getMessageWords();

        if(count($words) < 1) {
            return $this->replyDM('Usage: ..sw [shortcut]');
        }

        $shortCut = strtolower($words[0]);                
        $existingCharacter = $this->getCharacterByShortcut($shortCut);

        if (is_null($existingCharacter)) {
            return $this->replyDM('A character with the shortcut ' . $shortCut . ' doesn\'t exist.');
        }
        
        $this->messageInfo->characterDefaultSettings->setDefaultCharacterId($existingCharacter->getId());

        return null;
    }
}
