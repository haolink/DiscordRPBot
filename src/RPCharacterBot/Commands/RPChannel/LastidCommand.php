<?php

namespace RPCharacterBot\Commands\RPChannel;

use RPCharacterBot\Commands\RPCCommand;
use React\Promise\ExtendedPromiseInterface;

class LastIdCommand extends RPCCommand
{
    /**
     * Outputs the last id of a submitted user message to a user.
     *
     * @return ExtendedPromiseInterface|null
     */
    protected function handleCommandInternal(): ?ExtendedPromiseInterface
    {        
        $txt = 'No known former message.';

        if (!is_null($this->messageInfo->lastSubmittedMessages)) {
            $msg = $this->messageInfo->lastSubmittedMessages[count($this->messageInfo->_lastSubmittedMessages) - 1];
            $txt = 'ID: ' . $msg->id;
        }

        return $this->replyDM($txt);
    }
}
