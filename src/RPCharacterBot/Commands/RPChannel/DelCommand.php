<?php

namespace RPCharacterBot\Commands\RPChannel;

use React\Promise\ExtendedPromiseInterface;
use RPCharacterBot\Commands\RPCCommand;
use RPCharacterBot\Common\MessageCache;

class DelCommand extends RPCCommand
{  
    /**
     * Deletes the last message a user has submitted.
     *
     * @return ExtendedPromiseInterface|null
     */
    protected function handleCommandInternal(): ?ExtendedPromiseInterface
    {        
        if (!is_null($this->messageInfo->lastSubmittedMessage)) {
            $this->messageInfo->lastSubmittedMessage->delete()->done();

            MessageCache::removeFromCache($this->messageInfo->user->getId(), $this->messageInfo->channel->getId());
        }

        return null;
    }
}
