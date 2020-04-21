<?php

namespace RPCharacterBot\Bot;

use React\EventLoop\LoopInterface;
use CharlotteDunois\Yasmin\Client as DiscordClient;
use CharlotteDunois\Yasmin\Models\Message;
use React\MySQL\Factory as MysqlFactory;
use React\MySQL\Io\LazyConnection;
use RPCharacterBot\Common\Log\ConsoleOutput;
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

    public $marked = false;
    /**
     * On message received.
     *
     * @return void
     */
    private function onClientMessage(Message $message)
    {
        $guildId = $message->guild->id;

        $that = $this;
        Guild::fetchSingleByQuery($guildId)->then(function(Guild $guild) use($that) {  
            Bot::getInstance()->getOutput()->writeln($guild->getId());
        });
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
     * Get configuration array.
     *
     * @return array
     */ 
    public function getConfig() : array
    {
        return $this->config;
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
}

