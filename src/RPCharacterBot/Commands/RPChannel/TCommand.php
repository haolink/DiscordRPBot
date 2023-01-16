<?php

namespace RPCharacterBot\Commands\RPChannel;

use RPCharacterBot\Commands\RPCCommand;
use React\Promise\ExtendedPromiseInterface;
use RPCharacterBot\Common\Interfaces\StackableCommand;

class TCommand extends RPCCommand implements StackableCommand
{
    protected function handleCommandInternal(): ?ExtendedPromiseInterface
    {
        $words = $this->getMessageWords();

        if(count($words) < 2) {
            return $this->replyDM('Usage: ' . $this->messageInfo->quickPrefix . 't [shortcut] [message]');
        }

        $shortCut = strtolower($words[0]);                
        $existingCharacter = $this->getCharacterByShortcut($shortCut);

        if (is_null($existingCharacter)) {
            return $this->replyDM('A character with the shortcut ' . $shortCut . ' doesn\'t exist.');
        }

        if(count($words) > 1) {
            unset($words[0]);

            $content = implode(' ', $words);
            
            return $this->resubmitMessageAsCharacter($this->messageInfo->message, $existingCharacter, $content);
        } else {
            return null;
        }        
    }
}
