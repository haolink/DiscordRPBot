<?php
namespace RPCharacterBot\Common\MultiMessage;

use RPCharacterBot\Commands\RPCCommand;
use RPCharacterBot\Commands\RPChannel\DefaultHandler;
use RPCharacterBot\Common\MessageInfo;
use RPCharacterBot\Common\Helpers\BotHelper;
use RPCharacterBot\Common\Interfaces\StackableCommand;

class MessageContainer {
    /**
     * Original-Meldung.
     * @var MessageInfo
     */
    public $messageInfo;

    /**
     * All blocks of message data.
     * @var MessageBlock[]
     */
    public $blocks;

    /**
     * Private constructor.
     * @param MessageInfo $message 
     * @return void 
     */
    private function __construct(MessageInfo $info)
    {
        $this->messageInfo = $info;
        $this->blocks = array();
    }

    /**
     * Parse a message into a container format.
     * @param MessageInfo $info 
     * @return MessageContainer 
     */
    public static function parseMessage(MessageInfo $info) : MessageContainer  {
        $result = new MessageContainer($info);

        $messageText = $info->message->content ?? '';

        $lines = preg_split("/\r\n|\n|\r/", $messageText);
        
        $rpPrefix = $info->quickPrefix;
        
        $currentBlock = new MessageBlock($info, $result, new DefaultHandler($info));

        foreach ($lines as $line) {
            $words = explode(' ', $line);
            if (count($words) == 0) {
                if (is_string($currentBlock->content)) {
                    $currentBlock->content .= PHP_EOL;
                    continue;
                }
            }

            $firstWord = $words[0];
            $commandName = BotHelper::extractCommandName($firstWord, $rpPrefix);

            if (!is_null($commandName)) {
                $command = RPCCommand::searchCommand($commandName, $info);

                if (!is_null($command)) {
                    if (!is_null($currentBlock->content)) {
                        $result->blocks[] = $currentBlock;
                        $currentBlock = new MessageBlock($info, $result, $command);
                    }
                }
            }

            if (is_string($currentBlock->content)) {
                $currentBlock->content .= PHP_EOL . $line;
                continue;
            } else {
                $currentBlock->content = $line;
            }
        }

        if (!is_null($currentBlock->content)) {
            $result->blocks[] = $currentBlock;
        }

        foreach ($result->blocks as $block) {
            $block->content = trim($block->content);
        }        

        return $result;
    }

    /**
     * Verifies a message block collection for validity.
     * @return bool 
     */
    public function isValid() : bool {
        if (count($this->blocks) == 0) {
            return false;
        }
        
        if (count($this->blocks) == 1) {
            return true;
        }

        foreach ($this->blocks as $block) {
            if (!($block->handler instanceof StackableCommand)) {
                return false;
            }
        }

        return true;
    }
}

?>