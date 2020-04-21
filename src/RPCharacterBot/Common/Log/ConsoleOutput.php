<?php

namespace RPCharacterBot\Common\Log;

use RPCharacterBot\Interfaces\OutputLogInterface;

class ConsoleOutput implements OutputLogInterface
{
    public function write(string $message): void
    {
        echo $message;
    }

    public function writeln(string $message): void
    {
        echo $message . PHP_EOL;
    }
}
