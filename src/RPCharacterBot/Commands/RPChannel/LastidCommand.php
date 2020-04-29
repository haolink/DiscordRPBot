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

        if (!is_null($this->messageInfo->lastSubmittedMessage)) {
            $txt = 'ID: ' . $this->messageInfo->lastSubmittedMessage->id;
        }

        return $this->replyDM($txt);
    }
}
