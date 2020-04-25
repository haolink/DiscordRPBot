<?php

namespace RPCharacterBot\Commands\DM;

use React\Promise\ExtendedPromiseInterface;
use RPCharacterBot\Commands\DMCommand;

class ListCommand extends DMCommand
{
    public function handleCommandInternal() : ?ExtendedPromiseInterface
    {
        $characters = $this->messageInfo->characters;

        $text = 'You have ';
        $charCount = count($characters);
        switch ($charCount) {
            case 0: 
                $text.= 'no characters.';
                break;
            case 1: 
                $text.= 'one character:';
                break;
            default:
                $text.= $charCount . ' characters:';
                break;
        }

        if ($charCount > 0) {
            $text .=  PHP_EOL . 
                '```Shortcut         - Name' . PHP_EOL . 
                '========================================';
        }

        foreach ($characters as $character) 
        {
            $text .= PHP_EOL . str_pad($character->getCharacterShortname(), 16) . ' - ' . $character->getCharacterName();
        }        

        if ($charCount > 0)
        {
            $text .= '```';
        }        

        return $this->messageInfo->message->channel->send($text);
    }
}
