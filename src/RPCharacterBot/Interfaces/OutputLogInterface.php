<?php

namespace RPCharacterBot\Interfaces;

interface OutputLogInterface
{
    public function write(string $message) : void;

    public function writeln(string $message) : void;
}
