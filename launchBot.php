<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/config.php';

use RPCharacterBot\Bot\Bot;

$loop = \React\EventLoop\Factory::create();

$bot = new Bot($loop, $config);
$bot->run();

$loop->run();