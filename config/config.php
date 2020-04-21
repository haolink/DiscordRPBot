<?php

$token = include(__DIR__ . 'token.php');

$config = array(
    'imagemagick' => 'c:\\windows\\convert.exe',
    'db_connection' => 'rpbot:dSOrzpMSMooLQ9Ed@localhost:3316/rpbot',
    'discord_token' => $token,
    'caches' => array(
        'users' => 1000,
        'channels' => 200,
        'guilds' => 50
    )    
);

?>