<?php

namespace RPCharacterBot\Commands\DM;

use React\Promise\Deferred;
use RPCharacterBot\Commands\DMCommand;
use React\Promise\ExtendedPromiseInterface;
use RPCharacterBot\Model\Character;

class NewCommand extends DMCommand
{    
    /**
     * Command to create a new character.
     *
     * @return ExtendedPromiseInterface|null
     */
    protected function handleCommandInternal(): ?ExtendedPromiseInterface
    {
        $words = $this->getMessageWords();

        if(count($words) < 2) {
            return $this->replyDM('Usage: new [shortcut] [Character name]');
        }

        $shortCut = strtolower($words[0]);
        if(!preg_match('/^[a-z0-9\_\-]{1,16}$/i', $shortCut)) 
        {
            return $this->replyDM('The character short cut may only contain latin default letters and numbers and ' . 
                    'mustn\'t be longer than 16 characters.');
        }
        
        $existingCharacter = $this->getCharacterByShortcut($shortCut);

        if (!is_null($existingCharacter)) {
            return $this->replyDM('You already have a character with the shortcut ' . $shortCut . '.');
        }

        unset($words[0]);
        $fullName = implode(' ', $words);
        $errorMessage = $this->sanitiseName($fullName);

        if (!is_null($errorMessage)) {
            return $this->replyDM($errorMessage);
        }                

        $deferred = new Deferred();
        
        Character::createNewCharacter($this->messageInfo->user->getId(), $shortCut, $fullName)->
        then(function(Character $character) use ($deferred) {
            $this->replyDM('You\'ve successfully created ' . $character->getCharacterName() . 
               ' as a character. Their shortcut is: ' . $character->getCharacterShortname())->then(function() use ($deferred) {
                $deferred->resolve();
            });
        });

        return $deferred->promise();        
    }
}
