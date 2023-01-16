<?php

namespace RPCharacterBot\Commands\RPChannel;

use React\Promise\Deferred;
use RPCharacterBot\Commands\RPCCommand;
use React\Promise\ExtendedPromiseInterface;

class SwlastCommand extends RPCCommand
{
    /**
     * Outputs the last id of a submitted user message to a user.
     *
     * @return ExtendedPromiseInterface|null
     */
    protected function handleCommandInternal(): ?ExtendedPromiseInterface
    {
        if (is_null($this->messageInfo->lastSubmittedMessages)) {
            return null;            
        }

        $words = $this->getMessageWords();

        if(count($words) < 1) {
            return $this->replyDM('Usage: ..swlast [shortcut]');
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

        $that = $this;

        $deferred = new Deferred();

        $oldMessage = $this->messageInfo->lastSubmittedMessages[count($this->messageInfo->lastSubmittedMessages) - 1];

        $oldMessage->delete()->then(function() use ($deferred, $oldMessage, $existingCharacter, $that) {
            $that->resubmitMessageAsCharacter($oldMessage, $existingCharacter, $oldMessage->content)->then(function() use ($deferred) {
                $deferred->resolve();
            });
        });

        return $deferred->promise();        
    }
}