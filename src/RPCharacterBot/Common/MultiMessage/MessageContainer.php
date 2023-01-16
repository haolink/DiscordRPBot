<?php
namespace RPCharacterBot\Common\MultiMessage;

use React\Promise\Deferred;
use React\Promise\ExtendedPromiseInterface;
use RPCharacterBot\Commands\RPCCommand;
use RPCharacterBot\Commands\RPChannel\DefaultHandler;
use RPCharacterBot\Common\MessageInfo;
use RPCharacterBot\Common\Helpers\BotHelper;
use RPCharacterBot\Common\Interfaces\StackableCommand;
use RPCharacterBot\Common\MessageCache;

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
     * Error messages in container block.
     *
     * @var ?string[]
     */
    private $errors;

    /**
     * Is being set to true once this command is submitting. At this point no further submissions are acceptable anymore.
     *
     * @var boolean
     */
    private $isSubmitted;

    /**
     * Which command is currently being submitted.
     *
     * @var integer
     */
    private $submissionIndex;

    /**
     * Submission handler.
     *
     * @var Deferred
     */
    private $submissionDefer;

    /**
     * Messages returned by the webhooks.
     *
     * @var Message[]|null
     */
    private $returnedMessages;

    /**
     * Private constructor.
     * @param MessageInfo $message 
     * @return void 
     */
    private function __construct(MessageInfo $info)
    {
        $this->messageInfo = $info;
        $this->blocks = array();

        $this->isSubmitted = false;
        $this->errors = null;
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
                    }
                    $currentBlock = new MessageBlock($info, $result, $command);
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

        $result->validate();

        return $result;
    }

    /**
     * Verifies a message block collection for validity. Simple commands can handle errors internally.
     * @return void
     */
    private function validate() : void {
        if (count($this->blocks) == 0) {
            $this->errors = [];
            return;
        }
        
        if (count($this->blocks) == 1) {
            $this->errors = [];
            return;
        }

        $this->errors = [];
        foreach ($this->blocks as $block) {
            if ($block->handler instanceof StackableCommand) {
                $error = $block->handler->queryCallingError($block->content);

                if (!is_null($error)) {
                    $this->errors[] = $error;
                }
            } else {
                $this->errors[] = $block->handler->getCommandString($this->messageInfo->quickPrefix) . ' is not usable in multi segment messages';
            }
        }        
    }

    /**
     * Checks if the command queue can be submitted.
     *
     * @return boolean True if it's a single command (will validate itself) or if all multi commands are valid.
     */
    public function isValid() : bool {
        return (count($this->errors) == 0) && (count($this->blocks) > 0);
    }

    /**
     * Gets the command errors.
     *
     * @return array|null
     */
    public function getErrors() : ?array {
        return $this->errors;
    }

    /**
     * Submits all messages and deletes original.
     *
     * @return ExtendedPromiseInterface|null
     */
    public function submit() : ?ExtendedPromiseInterface {
        if (!$this->isValid() || $this->isSubmitted) { //Cannot submit twice
            return null;
        }
        
        $this->isSubmitted = true;

        $this->submissionDefer = new Deferred();

        $this->submissionIndex = 0;

        $this->returnedMessages = array();

        $that = $this;
        $this->messageInfo->bot->getLoop()->futureTick(function() use ($that) {
            $that->submitNext();
        });

        return $this->submissionDefer->promise();
    }

    /**
     * Submits the next message.
     *
     * @return void
     */
    private function submitNext() {
        if ($this->submissionIndex >= count($this->blocks)) {
            if (is_array($this->returnedMessages) && count($this->returnedMessages) > 0) {
                $userId = $this->messageInfo->message->author->id;
                $channelId = $this->messageInfo->message->channel->id;

                MessageCache::submitToCache($userId, $channelId, $this->returnedMessages);
                $this->returnedMessages = null;
            }

            $this->submissionDefer->resolve();
            return;
        }

        $block = $this->blocks[$this->submissionIndex];

        $that = $this;

        $options = [
            'ignoreAttachments' => ($this->submissionIndex != count($this->blocks) - 1),
            'messageLine' => $block->content
        ];

        $block->handler->handleCommand($options)->then(function() use($that, $block) {
            if ($block->handler instanceof RPCCommand) {
                $returnMessage = $block->handler->getWebhookReturnMessage();

                if (!is_null($returnMessage)) {
                    $that->returnedMessages[] = $returnMessage;
                }
            }
            
            $that->submissionIndex++;
            $that->submitNext();
        });        
    }
}

?>