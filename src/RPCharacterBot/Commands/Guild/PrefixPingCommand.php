<?php

namespace RPCharacterBot\Commands\Guild;

use React\Promise\ExtendedPromiseInterface;
use RPCharacterBot\Commands\GuildCommand;

class PrefixPingCommand extends GuildCommand
{
    /**
     * User executing this command requires the following permissions.
     *
     * @var array
     */
    protected static $REQUIRED_USER_PERMISSIONS = array('administrator');
    
    /**
     * Sets up the guild prefix for this server.
     *
     * @return ExtendedPromiseInterface|null
     */
    protected function handleCommandInternal(): ?ExtendedPromiseInterface
    {
        $words = $this->getMessageWords();

        if (count($words) < 1) {
            return $this->sendSelfDeletingReply('Usage: \@pingme prefix [serverprefix]');
        }

        $guildPrefix = mb_strtolower($words[0]);

        $wordLength = mb_strlen($guildPrefix);
        if ($wordLength < 1 || $wordLength > 6) {
            return $this->sendSelfDeletingReply('The server prefix should be 1 to 6 characters!');
        }

        if (!preg_match('/^[a-z\_\!\%\.\,\-\+\~]+$/u', $guildPrefix)) {
            return $this->sendSelfDeletingReply(
                'The server prefix may only use letters, as well as the following characters:' . PHP_EOL .
                '`_ ! % . , - + ~`');
        }

        $this->messageInfo->guild->setMainPrefix($guildPrefix);

        return $this->sendSelfDeletingReply('Server prefix has been set!');
    }
}
