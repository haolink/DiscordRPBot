<?php

namespace RPCharacterBot\Common;

use React\Promise\Deferred;
use React\Promise\ExtendedPromiseInterface;
use RPCharacterBot\Bot\Bot;
use RPCharacterBot\Common\Helpers\ClassHelper;
use CharlotteDunois\Yasmin\Client as DiscordClient;
use React\EventLoop\LoopInterface;

abstract class CommandHandler
{
    /** 
     * Message information 
     * 
     * @var MessageInfo
     */ 
    protected $messageInfo;

    /**
     * Main bot.
     *
     * @var Bot
     */
    protected $bot;

    /**
     * Discord client.
     *
     * @var DiscordClient
     */
    protected $client;

    /**
     * Data loop.
     *
     * @var LoopInterface
     */
    protected $loop;

    /**
     * Classes for commands.
     *
     * @var array
     */
    private static $commandClasses = array();
    
    /**
     * Constructor.
     * 
     * @param MessageInfo $info
     */
    protected function __construct(MessageInfo $info = null)
    {
        $this->messageInfo = $info;
        $this->bot = Bot::getInstance();
        $this->client = $this->bot->getClient();
        $this->loop = $this->bot->getLoop();
    }

    /**
     * Handle an actual command.
     *
     * @return PromiseInterface|null
     */
    abstract protected function handleCommandInternal() : ?ExtendedPromiseInterface;

    /**
     * Checks whether I can handle the given command and if so passes it onto
     * the actual handler.
     *
     * @return PromiseInterface
     */
    public function handleCommand() : ExtendedPromiseInterface
    {
        $deferred = new Deferred();

        $that = $this;
        $this->loop->futureTick(function() use ($that, $deferred) {
            $res = $that->handleCommandInternal();

            if($res instanceof ExtendedPromiseInterface) {
                $res->then(function() use ($deferred) {
                    $deferred->resolve();
                });
            } else {
                $deferred->resolve();
            }            
        });

        return $deferred->promise();
    }

    /**
     * Check if a command exists and if so returns the command handler.
     *
     * @param string $command
     * @param MessageInfo $info
     * @return CommandHandler|null
     */
    public static function searchCommand($command, ?MessageInfo $info = null) : ?CommandHandler
    {
        $class = get_called_class();
        $command = strtolower($command);

        if (!array_key_exists($class, self::$commandClasses)) {
            if(!property_exists($class, 'COMMAND_NAMESPACE')) {
                return null;
            }
            
            $namespace = $class::$COMMAND_NAMESPACE;

            $classes = ClassHelper::findRecursive($namespace);

            $commands = array();

            foreach ($classes as $foundClass) {
                $parents = class_parents($foundClass);
                if(!in_array($class, $parents)) {
                    continue;
                }
                $classParts = explode('\\', $foundClass);
                $className = $classParts[count($classParts) - 1];
                if ((substr($className, -7)) == 'Command') {
                    $commandName = strtolower(substr($className, 0, -7));
                    $commands[$commandName] = $foundClass;
                }
            }

            self::$commandClasses[$class] = $commands;
        }

        if (array_key_exists($command, self::$commandClasses[$class])) {
            $className = '\\' . self::$commandClasses[$class][$command];
            return new $className($info);
        }
        
        return null;
    }
}