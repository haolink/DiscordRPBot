<?php

namespace RPCharacterBot\Commands;

use RPCharacterBot\Common\CommandHandler;

abstract class DMCommand extends CommandHandler
{
    protected static $COMMAND_NAMESPACE = 'RPCharacterBot\\Commands\\DM';

    /**
     * Checks a name for Discord compatibility.
     * If it fails it will return an error message.
     *
     * @param string $name
     * @return string|null
     */
    protected function sanitiseName(string &$fullName) : ?string
    {
        $fullName = trim(preg_replace('/[^\S ]/u', '', $fullName));

        if (mb_strlen($fullName) > 32 || mb_strlen($fullName) < 2 || 
            preg_match('/^(clyde|everyone|discordtag|everyone|here|' .
                '((.*)[\@\#\:](.*))|((.*)\`\`\`(.*))|((.*)[ ]{3,}(.*)))$/ui', $fullName)) 
        {
            return 
                'The character name violates the Discord naming guidelines. Character names may be:' . PHP_EOL .
                '-Names must be at least two characters long, they mustn\'t be longer than 32.' . PHP_EOL .                 
                '-Names cannot contain @, #, : or \\`\\`\\`' . PHP_EOL . 
                '-The following nasmes are not allowed: discordtag, everyone, here, clyde.'
            ;
        }

        return null;
    }
}
