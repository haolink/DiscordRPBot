<?php

namespace RPCharacterBot\Bot;

use Ratchet\ConnectionInterface;
use Ratchet\WebSocket\MessageComponentInterface;
use React\EventLoop\LoopInterface;
use RPCharacterBot\Model\ChannelUser;
use RPCharacterBot\Model\Character;
use RPCharacterBot\Model\GuildUser;
use RPCharacterBot\Model\User;

class SocketServer implements MessageComponentInterface
{
    /**
     * Loop interface.
     *
     * @var LoopInterface
     */
    private $loop;

    /**
     * Bot.
     *
     * @var Bot
     */
    private $bot;

    public function __construct(LoopInterface $loop, Bot $bot)
    {
        $this->loop = $loop;
        $this->bot = $bot;
    }

    /**
     * Handle the new connection when it's received.
     *
     * @param  ConnectionInterface $conn
     * @return void
     */
    public function onOpen(ConnectionInterface $conn)
    {
        $clientIp = $conn->remoteAddress;
        $conn->clientIp = $clientIp;

        $this->bot->writeln('Incoming connection from ' . $conn->clientIp);
    }

    /**
     * A new message was received from a connection.  Dispatch
     * that message to all other connected clients.
     *
     * The message can be a list of commands.  We only want to store the draw state, the following will listen
     * for various messages that the server needs to keep track of.
     *
     * @param  ConnectionInterface $from
     * @param  String              $msg
     * @return void
     */
    public function onMessage(ConnectionInterface $from, $msg) {
        $msgData = null;
        $command = null;
        $payload = null;
        try {
            $msgData = @json_decode($msg, true);
            if (array_key_exists('command', $msgData) && array_key_exists('payload', $msgData)) {
                $command = $msgData['command'];
                $payload = $msgData['payload'];
            } else {
                $msgData = null;
            }
        } catch(\Exception $ex) {
            $msgData = null;
            $command = null;
            $payload = null;
        }

        if (is_null($msgData) || is_null($command)) {            
            return;
        }

        $methodName = 'parse_' . strtolower($command);
        if (method_exists($this, $methodName)) {
            call_user_func_array(array($this, $methodName), array($payload));
        } else {
            $this->bot->writeln('Unknown method ' . $methodName);
        }
    }

    /**
     * Forces a clearing of the cache of the user with the given ID.
     *
     * @param string $userId
     * @return void
     */
    private function parse_userclear($userId) 
    {
        $this->bot->writeln('Received cache refresh for user ' . $userId);
        Character::uncacheByUserId($userId);
        GuildUser::uncacheByUserId($userId);
        ChannelUser::uncacheByUserId($userId);
        User::uncacheById($userId);
    }

    /**
     * The connection has closed, remove it from the clients list.
     * @param  ConnectionInterface $conn
     * @return void
     */
    public function onClose(ConnectionInterface $conn)
    {
        $this->bot->writeln('User on IP ' . $conn->clientIp . ' disconnected.');        
    }

    /**
     * An error on the connection has occured, this is likely due to the connection
     * going away.  Close the connection.
     * @param  ConnectionInterface $conn
     * @param  Exception           $e
     * @return void
     */
    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        $conn->close();
    }
}
