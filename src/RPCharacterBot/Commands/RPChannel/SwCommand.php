<?php

namespace RPCharacterBot\Commands\RPChannel;

use RPCharacterBot\Commands\RPCCommand;
use React\Promise\ExtendedPromiseInterface;
use RPCharacterBot\Common\Interfaces\StackableCommand;

class SwCommand extends RPCCommand implements StackableCommand
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

        if ($existingCharacter == $this->messageInfo->currentCharacter) {
            return null; //No change??
        }
        
        $this->messageInfo->characterDefaultSettings->setFormerCharacterId($this->messageInfo->currentCharacter->getId());
        $this->messageInfo->characterDefaultSettings->setDefaultCharacterId($existingCharacter->getId());

        if(count($words) > 1) {
            unset($words[0]);

            $content = implode(' ', $words);
            
            return $this->resubmitMessageAsCharacter($this->messageInfo->message, $existingCharacter, $content);
        } else {
            return null;
        }        
    }
}
