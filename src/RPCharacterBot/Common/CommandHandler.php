<?php

namespace RPCharacterBot\Common;

use Cake\Utility\Text;
use React\Promise\Deferred;
use React\Promise\ExtendedPromiseInterface;
use RPCharacterBot\Bot\Bot;
use RPCharacterBot\Common\Helpers\ClassHelper;
use Discord\Discord as DiscordClient;
use Discord\Parts\User\Member;
use Discord\Parts\Channel\Message;
use Discord\Parts\Channel\Channel;
use Discord\Parts\Permissions\Permission;
use Discord\Parts\Permissions\RolePermission;
use React\EventLoop\LoopInterface;
use RPCharacterBot\Model\Character;

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
    protected function __construct(MessageInfo $info)
    {
        $this->messageInfo = $info;
        $this->bot = Bot::getInstance();
        $this->client = $this->bot->getClient();
        $this->loop = $this->bot->getLoop();
    }

    /**
     * Formats a list of permissions in a readable way.
     *
     * @param array $permissionList
     * @return string
     */
    private function createPermissionListString(array $permissionList) : string
    {
        $readableStrings = array();
        foreach($permissionList as $permissionName) {
            $permissionWords = explode('_', $permissionName);
            foreach($permissionWords as &$permissionWord) {
                $permissionWord = ucfirst(strtolower($permissionWord));
            }
            unset($permissionWord);
            $readableStrings[] = '"' . implode(' ', $permissionWords) . '"';
        }
        $res = $readableStrings[0];

        for($i = 1; $i < count($readableStrings); $i++) {
            if($i < count($readableStrings) - 1) {
                $res .= ', ';
            } else {
                $res .= ' and ';                
            }
            $res .= $readableStrings[$i];
        }

        return $res;
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
        $textChannel = $this->messageInfo->message->channel;
        $class = get_called_class();

        /**
         * Check if the user has the required permissions to run this command.
         */
        if (property_exists($class, 'REQUIRED_USER_PERMISSIONS') && 
           (!$this->messageInfo->isDM) && ($textChannel->type == Channel::TYPE_TEXT) &&
           is_array($class::$REQUIRED_USER_PERMISSIONS) && (count($class::$REQUIRED_USER_PERMISSIONS) > 0)) {
            /** @var Channel $textChannel */
            /** @var array $requiredPermissions */
            $requiredPermissions = $class::$REQUIRED_USER_PERMISSIONS;            
            
            $this->messageInfo->message->channel->guild->members->fetch($this->messageInfo->message->author->id)->
                    then(function(Member $gm) use ($that, $deferred, $textChannel, $requiredPermissions) {
                $permissions = $gm->getPermissions($textChannel);
                $matched = true;
                foreach(RolePermission::getPermissions() as $permString => $permNumber) {
                    if (in_array($permString, $requiredPermissions)) {
                        if (!$permissions->{$permString}) {
                            $matched = false;
                            break;
                        }
                    }
                }

                if ($matched) {
                    $that->handleCommandUserPermissionAvailable($deferred);
                } else {
                    $text = 'You must have the following permissions to run this command: ' . 
                        $that->createPermissionListString($requiredPermissions);
                    $that->replyDM($text)->then(function() use ($deferred) {
                        $deferred->resolve();
                    });
                }
            });
        } else {
            $this->loop->futureTick(function() use ($deferred, $that) {
                $that->handleCommandUserPermissionAvailable($deferred);
            });            
        }

        return $deferred->promise();
    }

    /**
     * Channel permissions are available.
     *
     * @param Deferred $deferred
     * @return void
     */
    private function handleCommandUserPermissionAvailable(Deferred $deferred) 
    {
        $that = $this;
        $textChannel = $this->messageInfo->message->channel;
        $class = get_called_class();

        /**
         * Check if the user has the required permissions to run this command.
         */
        if (property_exists($class, 'REQUIRED_CHANNEL_PERMISSIONS') && 
           (!$this->messageInfo->isDM) && ($textChannel->type == Channel::TYPE_TEXT) &&
           is_array($class::$REQUIRED_CHANNEL_PERMISSIONS) && (count($class::$REQUIRED_CHANNEL_PERMISSIONS) > 0)) {
            /** @var Channel $textChannel */
            /** @var array $requiredPermissions */
            $requiredPermissions = $class::$REQUIRED_CHANNEL_PERMISSIONS;            
            
            $this->messageInfo->message->channel->guild->members->fetch($this->client->user->id)->
                    then(function(Member $gm) use ($that, $deferred, $textChannel, $requiredPermissions) {
                $permissions = $gm->getPermissions($textChannel);
                $matched = true;
                foreach(RolePermission::getPermissions() as $permString => $permNumber) {
                    if (in_array($permString, $requiredPermissions)) {
                        if (!$permissions->{$permString}) {
                            $matched = false;
                            break;
                        }
                    }
                }

                if ($matched) {
                    $that->handleCommandChannelPermissionAvailable($deferred);
                } else {
                    $text = 'The bot must have the following permissions in this channel to run this command: ' . 
                        $that->createPermissionListString($requiredPermissions);
                    $that->replyDM($text)->then(function() use ($deferred) {
                        $deferred->resolve();
                    });
                }
            });
        } else {
            $this->handleCommandChannelPermissionAvailable($deferred);            
        }
    }

    /**
     * Channel permission is available.
     *
     * @param Deferred $deferred
     * @return void
     */
    private function handleCommandChannelPermissionAvailable(Deferred $deferred)
    {
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
    }

    /**
     * Check if a command exists and if so returns the command handler.
     *
     * @param string $command
     * @param MessageInfo $info
     * @return CommandHandler|null
     */
    public static function searchCommand($command, MessageInfo $info, string $commandAttachment = '') : ?CommandHandler
    {
        $class = get_called_class();
        $command = mb_strtolower($command) . $commandAttachment;

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
                    $commandClassName = substr($className, 0, -7);
                    $commandName = strtolower(substr($commandClassName, 0, 1)) . substr($commandClassName, 1);
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

    /**
     * Replies to a message depending on the message type.
     *
     * @param string $message
     * @return ExtendedPromiseInterface
     */
    protected function reply(string $message) : ExtendedPromiseInterface
    {        
        return $this->messageInfo->message->channel->sendMessage($message);
    }

    /**
     * Forces a DM reply.
     *
     * @param string $message
     * @return ExtendedPromiseInterface
     */
    protected function replyDM(string $message) : ExtendedPromiseInterface
    {
        if ($this->messageInfo->isDM) {
            return $this->reply($message);
        } else {
            $deferred = new Deferred();
            
            $that = $this;
            $this->messageInfo->message->author->sendMessage($message)->then(                
                function() use ($deferred) {
                    $deferred->resolve();
                });

            return $deferred->promise();
        }
    }

    /**
     * Gets a character by their shortcut.
     *
     * @param string $shortcut
     * @return Character|null
     */
    protected function getCharacterByShortcut(string $shortcut) : ?Character
    {
        $selectedCharacter = null;

        foreach($this->messageInfo->characters as $character) {
            if($character->getCharacterShortname() == $shortcut) {
                $selectedCharacter = $character;
                break;
            }
        }

        return $selectedCharacter;        
    }

    /**
     * Splits the message into words beginning the 2nd word (1st word is the command).
     * 
     * @return array
     */
    protected function getMessageWords() : array
    {
        $messageParts = explode(' ', $this->messageInfo->message->content);
        if (count($messageParts) <= 1) {
            return [];
        }

        if (mb_substr($messageParts[0], 0, 2) == '<@') { //This is a ping            
            if (count($messageParts) <= 2) {
                return [];
            }
            unset($messageParts[1]);
        }
        unset($messageParts[0]);
        return array_values($messageParts);    
    }

    /**
     * Attempts to read a boolean value out of a string.
     *
     * @param string $input
     * @return boolean|null
     */
    protected function readBooleanString(string $input) : ?bool
    {
        $input = mb_strtolower($input);
        if(in_array($input, array('1', 'on', 'yes', 'true', 'enabled'))) {
            return true;
        }
        if(in_array($input, array('0', '-1', 'off', 'no', 'false', 'disabled'))) {
            return false;
        }
        return null;
    }

    /**
     * Sends a message which will delete itself after some time.
     *
     * @param string $messageText
     * @param int $timeout Time until a message is being deleted.
     * @return ExtendedPromiseInterface
     */
    protected function sendSelfDeletingReply(string $messageText, int $timeout = 30) : ExtendedPromiseInterface
    {
        $deferred = new Deferred();

        $this->reply($messageText)->then(function(Message $message) use ($deferred, $timeout) {
            $messageToDelete = $message;
            $this->loop->addTimer($timeout, function() use ($messageToDelete) {
                $messageToDelete->delete()->done();
            });
            $deferred->resolve();
        });

        return $deferred->promise();
    }
}
