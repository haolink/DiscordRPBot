<?php

namespace RPCharacterBot\Commands\RPChannel;

use RPCharacterBot\Commands\RPCCommand;
use React\Promise\ExtendedPromiseInterface;

class BackCommand extends RPCCommand
{
    /**
     * Switches to the last used character.
     *
     * @return ExtendedPromiseInterface|null
     */
   protected function handleCommandInternal(): ?ExtendedPromiseInterface
    {
        $words = $this->getMessageWords();

        $content = implode(' ', $words);
        
        if (is_null($this->messageInfo->formerCharacter) || ($this->messageInfo->formerCharacter == $this->messageInfo->currentCharacter)) {
            return null;
        }
        
        $this->messageInfo->characterDefaultSettings->setFormerCharacterId($this->messageInfo->currentCharacter->getId());
        $this->messageInfo->characterDefaultSettings->setDefaultCharacterId($this->messageInfo->formerCharacter->getId());

        if(!empty($content)) {
            return $this->resubmitMessageAsCharacter($this->messageInfo->message, $this->messageInfo->formerCharacter, $content);
        } else {
            return null;
        }        
    }
}
