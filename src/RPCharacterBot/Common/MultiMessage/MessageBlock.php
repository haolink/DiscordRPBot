<?php
namespace RPCharacterBot\Common\MultiMessage;

use RPCharacterBot\Common\CommandHandler;
use RPCharacterBot\Common\MessageInfo;

class MessageBlock {
    /**
     * Handler for the Block.
     * @var CommandHandler
     */
    public CommandHandler $handler;

    /**
     * Original Message.
     * @var MessageInfo
     */
    public MessageInfo $fullMessage;

    /**
     * Message block containing this message.
     * @var MessageContainer
     */
    public MessageContainer $container;

    /**
     * Text content of the line to parse.
     * @var ?string
     */
    public ?string $content;    

    public function __construct(MessageInfo $message, MessageContainer $container, CommandHandler $handler)
    {
        $this->fullMessage = $message;
        $this->container = $container;
        $this->handler = $handler;

        $this->content = null;
    }
}

?>