<?php

namespace RPCharacterBot\Bot;

use React\EventLoop\LoopInterface;
use CharlotteDunois\Yasmin\Client as DiscordClient;
use CharlotteDunois\Yasmin\Models\GuildMember;
use CharlotteDunois\Yasmin\Models\Message;
use CharlotteDunois\Yasmin\Models\Permissions;
use CharlotteDunois\Yasmin\Models\TextChannel;
use React\MySQL\Factory as MysqlFactory;
use React\MySQL\Io\LazyConnection;
use RPCharacterBot\Commands\DMCommand;
use RPCharacterBot\Commands\RPCCommand;
use RPCharacterBot\Commands\RPChannel\DefaultHandler;
use RPCharacterBot\Commands\RPChannel\OOCHandler;
use RPCharacterBot\Common\Log\ConsoleOutput;
use RPCharacterBot\Common\MessageInfo;
use RPCharacterBot\Exception\BotException;
use RPCharacterBot\Interfaces\OutputLogInterface;
use RPCharacterBot\Model\Guild;

class Bot
{
    /**
     * Singleton.
     *
     * @var Bot
     */
    private static $SINGLETON = null;

    /**
     * Bot loop.
     *
     * @var LoopInterface
     */
    private $loop;

    /**
     * Configuration array.
     *
     * @var array
     */
    private $config;

    /**
     * Discord client.
     *
     * @var DiscordClient
     */
    private $client;

    /**
     * Discord token.
     *
     * @var string
     */
    private $token;

    /**
     * Logger.
     *
     * @var OutputLogInterface
     */
    private $output;

    /**
     * Database connection.
     *
     * @var LazyConnection
     */
    private $dbConnection;

    /**
     * Are we connected?
     *
     * @var bool
     */
    private $connected;

    /**
     * Retrieves the bot instance.
     *
     * @return Bot|null
     */
    public static function getInstance() : ?Bot
    {
        return self::$SINGLETON;
    }

    /**
     * Creator of the main bot core.
     *
     * @param LoopInterface $loop
     * @param array $config
     */
    public function __construct(LoopInterface $loop, array $config)
    {        
        if(!is_null(self::$SINGLETON)) {
            throw new BotException('Singleton already created!');
        }
        self::$SINGLETON = $this;        

        $this->output = new ConsoleOutput();
        $this->connected = false;

        $this->loop = $loop;
        $this->config = $config;
        $this->token = $config['discord_token'];

        $this->client = new DiscordClient(array(), $loop);
        $this->client->on('error', \Closure::fromCallable(array($this, 'onClientError')));
        $this->client->on('ready', \Closure::fromCallable(array($this, 'onClientReady')));
        $this->client->on('message', \Closure::fromCallable(array($this, 'onClientMessage')));

        $dbFactory = new MysqlFactory($loop);
        $this->dbConnection = $dbFactory->createLazyConnection($config['db_connection']);        
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    private function onSigint() {
        $this->output->writeln('Ctrl-C received!');
    }

    /**
     * Handles errors on Discord side.
     *
     * @param mixed $error
     * @return void
     */
    private function onClientError($error) 
    {
        $this->output->writeln($error);
    }

    /**
     * Output a successful connection.
     *
     * @return void
     */
    private function onClientReady()
    {        
        $this->output->writeln('Logged in as ' . $this->client->user->tag . ' created on ' . 
            $this->client->user->createdAt->format('d.m.Y H:i:s'));
    }

    /**
     * Run the bot.
     *
     * @return bool
     */
    public function run() : bool
    {
        if($this->connected) {
            return false;
        }
        $this->connected = true;
        $this->client->login($this->token)->done();

        return true;
    }

    /**
     * Get configuration array or alternatively a single value from it.
     *
     * @return mixed
     */ 
    public function getConfig($key = null, $defaultValue = null)
    {
        if(is_null($key)) {
            return $this->config;
        }        

        if(array_key_exists($key, $this->config)) {
            return $this->config[$key];
        }

        return $defaultValue;
    }

    /**
     * Get bot loop.
     *
     * @return LoopInterface
     */ 
    public function getLoop() : LoopInterface
    {
        return $this->loop;
    }

    /**
     * Get database connection.
     *
     * @return LazyConnection
     */ 
    public function getDbConnection() : LazyConnection
    {
        return $this->dbConnection;
    }

    /**
     * Get logger.
     *
     * @return  OutputLogInterface
     */ 
    public function getOutput()
    {
        return $this->output;
    }

    /**
     * Writes a message to the output log.
     *
     * @param string $message
     * @return void
     */
    public function write(string $message)
    {
        $this->output->write($message);
    }

    /**
     * Writes a message with newline to the output log.
     *
     * @param string $message
     * @return void
     */
    public function writeln(string $message)
    {
        $this->output->writeln($message);
    }

    /**
     * On message received.
     *
     * @param Message $message
     * @return void
     */
    private function onClientMessage(Message $message)
    {
        $that = $this;

        if ($message->author->bot) {
            return;
        }
        
        MessageInfo::parseMessage($this, $message)->then(function(MessageInfo $messageInfo) use($that) {
            $that->messageDataAvailable($messageInfo);
        });
    }

    /**
     * Debugging class output.
     * 
     * @return string
     */
    private function debugClass($object) : string
    {
        if(is_null($object)) {
            return 'null';
        }
        return get_class($object);
    }

    /**
     * Message and user data are available.
     *
     * @param MessageInfo $info
     * @return void
     */
    private function messageDataAvailable(MessageInfo $info)
    {       
        if ($info->isDM) {
            $this->handleDmCommands($info);
        } elseif ($info->isRPChannel) {
            if ($this->handleRPChannelCommands($info)) {
                return;
            }
            if ($this->handleRPChannelOoc($info)) {
                return;
            }
            $this->handleRPChannelMessage($info);            
        } else {
            /** @var TextChannel $textChannel */
            /*$textChannel = $info->message->channel;
            $info->message->guild->fetchMember($this->client->user->id)->then(function(GuildMember $member) use($textChannel, $info) {
                $permissions = $textChannel->permissionsFor($member);
                $permissionList = array();
                foreach(Permissions::PERMISSIONS as $permString => $permNumber) {
                    if ($permissions->has($permNumber)) {
                        $permissionList[] = $permString;
                    }
                }
                print_r($permissionList);
            });*/
        }     
    }

    /**
     * Handles a DM message.
     *
     * @param MessageInfo $info
     * @return bool Was able to handle a command.
     */
    private function handleDmCommands(MessageInfo $info) : bool
    {
        $messageText = $info->message->content ?? '';
        $words = explode(' ', $messageText);
        if (count($words) == 0) {
            return false;
        }

        $firstWord = $words[0];
        $command = DMCommand::searchCommand($firstWord, $info);

        if(!is_null($command)) {
            $command->handleCommand()->done();

            return true;
        }
        return false;
    }

    /**
     * Extracts a prefixed command.
     *
     * @param string $text
     * @param string $prefix
     * @return string|null
     */
    private function extractCommandName($text, $prefix) : ?string
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

    /**
     * Handles messages within an RP channel.
     *
     * @param MessageInfo $info
     * @return bool Was able to handle a command.
     */
    private function handleRPChannelCommands(MessageInfo $info)
    {
        $messageText = $info->message->content ?? '';
        $words = explode(' ', $messageText);
        if (count($words) == 0) {
            return false;
        }

        $firstWord = $words[0];
        $commandName = $this->extractCommandName($firstWord, $info->quickPrefix);

        if (is_null($commandName)) {
            return false;
        }

        $command = RPCCommand::searchCommand($commandName, $info);
        if (!is_null($command)) {
            $command->handleCommand()->done();
            $info->message->delete()->done();

            return true;
        }
        return false;
    }

    /**
     * Handles OOC messages.
     *
     * @param MessageInfo $info
     * @return void
     */
    private function handleRPChannelOoc(MessageInfo $info)
    {
        if (!$info->channel->getAllowOoc()) {
            return false;
        }

        $messageText = $info->message->content ?? '';
        
        $cmd = $this->extractCommandName($messageText, $info->oocSequence);

        if (!is_null($cmd)) {
            $oocHandler = new OOCHandler($info);
            $oocHandler->handleCommand()->done();
            return true;
        }

        return false;
    }

    /**
     * Handles a basic message in an RP command.
     *
     * @param MessageInfo $info
     * @return bool
     */
    private function handleRPChannelMessage(MessageInfo $info) : bool
    {
        $defaultHandler = new DefaultHandler($info);
        $defaultHandler->handleCommand();
        return true;
    }

    /**
     * Get discord client.
     *
     * @return  DiscordClient
     */ 
    public function getClient() : DiscordClient
    {
        return $this->client;
    }
}

