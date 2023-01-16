<?php

namespace RPCharacterBot\Common\Interfaces;

interface StackableCommand {
    /**
     * Checks for a syntax parsing error. If result is null, everything's fine.
     * @return null|string 
     */
    public function queryCallingError(string $messageBody) : ?string;
}