<?php

namespace RPCharacterBot\Bot;

use React\EventLoop\LoopInterface;
use CharlotteDunois\Yasmin\Client as DiscordClient;
use CharlotteDunois\Yasmin\Models\Guild as DiscordGuild;
use CharlotteDunois\Yasmin\Models\Message;
use CharlotteDunois\Yasmin\Models\Role;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use React\MySQL\Factory as MysqlFactory;
use React\MySQL\Io\LazyConnection;
use RPCharacterBot\Commands\DMCommand;
use RPCharacterBot\Commands\GuildCommand;
use RPCharacterBot\Commands\RPCCommand;
use RPCharacterBot\Commands\RPChannel\DefaultHandler;
use RPCharacterBot\Commands\RPChannel\OOCHandler;
use RPCharacterBot\Common\Log\ConsoleOutput;
use RPCharacterBot\Common\MessageCache;
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
        
        if (array_key_exists('websocket', $config) && $config['websocket']['enabled']) {
            $listen = $config['websocket']['listen'];
            $port = $config['websocket']['port'];
            $this->writeln('Initialising websocket on ' . $listen . ':' . $port);

            $socket = new \React\Socket\Server($listen . ':' . $config['websocket']['port'], $loop);

            $mainServer = new SocketServer($loop, $this);

            $webSocketServer = new IoServer(new HttpServer(new WsServer($mainServer)), $socket, $loop);
        }                
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
        if($error instanceof \Exception) {
            $this->output->writeln($error->getMessage());
        } else {
            $this->output->writeln(get_class($error));
        }        
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
            MessageCache::identifyBotMessage($message);
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
            if ($this->handleGuildCommand($info)) {
                return;
            }            
            if ($this->handleRPChannelCommand($info)) {
                return;
            }
            if ($this->handleRPChannelOoc($info)) {
                return;
            }
            $this->handleRPChannelMessage($info);            
        } else {
            if ($this->handleGuildCommand($info)) {
                return;
            }            
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
    private function handleRPChannelCommand(MessageInfo $info)
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
     * Undocumented function
     *
     * @param string $word
     * @param DiscordGuild $guild
     * @return boolean
     */
    private function isAPingAtMe(string $word, DiscordGuild $guild) : bool {
        if(mb_strlen($word) < 22) {
            return false;
        }

        $firstTwo = mb_substr($word, 0, 2);
        if ($firstTwo != '<@') {
            return false;
        }

        $lastOne = mb_substr($word, -1, 1);
        if ($lastOne != '>') {
            return false;
        }

        $initialCharacter = 2;
        $thirdLetter = mb_substr($word, 2, 1);
        if (in_array($thirdLetter, array('&', '!'))) {
            $initialCharacter = 3;
        }

        $pingedId = mb_substr($word, $initialCharacter, -1);

        if (!preg_match('/^[0-9]+$/u', $pingedId)) {
            return false;
        }

        $myIds = array($this->client->user->id);

        foreach ($guild->me->roles->all() as $role) {
            /** @var Role $role */
            if ($role->mentionable || $role->managed) {
                $myIds[] = $role->id;
            }
        }

        if (in_array($pingedId, $myIds)) {            
            return true;
        }

        return false;
    }

    /**
     * Handles a command coming from a guild.
     *
     * @param MessageInfo $info
     * @return boolean
     */
    private function handleGuildCommand(MessageInfo $info) : bool
    {
        $messageText = $info->message->content ?? '';

        $words = explode(' ', $messageText);

        if (count($words) == 0) {
            return false;
        }

        $firstWord = $words[0];

        //Exception for prefix command        
        
        $commandAttachment = '';
        if($this->isAPingAtMe($firstWord, $info->message->guild)) {
            if (count($words) == 1) {
                return false;
            }
            $commandName = $words[1];
            $commandAttachment = 'Ping';
        } else {
            $commandName = $this->extractCommandName($firstWord, $info->mainPrefix);
        }        

        if (is_null($commandName)) {
            return false;
        }

        $command = GuildCommand::searchCommand($commandName, $info, $commandAttachment);
        if (!is_null($command)) {
            $command->handleCommand()->done();

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

        $matchBrackets = preg_match('/^(\[(.*)\]|\((.*)\)|\{(.*)\})$/us', $messageText);

        if (!is_null($cmd) || $matchBrackets) {
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

