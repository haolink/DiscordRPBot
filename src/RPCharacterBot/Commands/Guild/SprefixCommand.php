<?php

namespace RPCharacterBot\Commands\Guild;

use RPCharacterBot\Commands\GuildCommand;
use React\Promise\ExtendedPromiseInterface;

class SprefixCommand extends GuildCommand
{    
    /**
     * User executing this command requires the following permissions.
     *
     * @var array
     */
    protected static $REQUIRED_USER_PERMISSIONS = array('ADMINISTRATOR');
    
    /**
     * Sets up the RP command prefix for a guild.
     *
     * @return ExtendedPromiseInterface|null
     */
    protected function handleCommandInternal(): ?ExtendedPromiseInterface
    {
        $words = $this->getMessageWords();

        if(count($words) < 1) {
            return $this->sendSelfDeletingReply('Usage: ' . $this->messageInfo->mainPrefix . 'sprefix [characters]');
        }

        $newPrefix = mb_strtolower($words[0]);

        if (!preg_match('/^([\.\,\-\_\;\:\#\*\+\/\\\$\%\&\?])\1{0,3}$/u', $newPrefix)) {
            return $this->replyDM('The DM pattern must be up to 4 repeating characters of one of the following characters:' . PHP_EOL .
                '` . , - _ ; : # * + / \\ $ % & ?`');
        }

        $this->messageInfo->guild->setQuickPrefix($newPrefix);

        return $this->sendSelfDeletingReply('Server prefix for RP commands has been set to ' . $newPrefix);
    }
}