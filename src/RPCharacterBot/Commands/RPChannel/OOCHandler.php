<?php

namespace RPCharacterBot\Commands\RPChannel;

use React\Promise\Deferred;
use RPCharacterBot\Commands\RPCCommand;
use RPCharacterBot\Common\MessageInfo;
use React\Promise\ExtendedPromiseInterface;

class OOCHandler extends RPCCommand
{
    /**
     * Default handler can be directly initiated.
     *
     * @param MessageInfo $messageInfo
     */
    public function __construct(MessageInfo $messageInfo)
    {
        parent::__construct($messageInfo);
    }

    /**
     * Replaces a user message.
     *
     * @return ExtendedPromiseInterface|null
     */
    protected function handleCommandInternal(): ?ExtendedPromiseInterface
    {
        //Nothing to do.
        return null;
    }
}