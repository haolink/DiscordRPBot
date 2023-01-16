<?php

namespace RPCharacterBot\Common\Helpers;

class BotHelper {
    /**
     * Extracts a prefixed command.
     *
     * @param string $text
     * @param string $prefix
     * @return string|null
     */
    public static function extractCommandName($text, $prefix) : ?string
    {
        $prefixLength = mb_strlen($prefix);
        $wordLength = mb_strlen($text);

        if ($prefixLength >= $wordLength) {
            return null;
        }

        $firstLetters = mb_substr($text, 0, $prefixLength);

        if ($firstLetters == $prefix) {
            return mb_substr($text, $prefixLength);
        } else {
            return null;
        }
    }
}