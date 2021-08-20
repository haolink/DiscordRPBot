<?php

namespace RPCharacterBot\Common\Helpers;

use Discord\Discord;
use Discord\Parts\Channel\Channel as DiscordChannel;
use Discord\Parts\Thread\Member;
use Discord\Parts\Thread\Thread;
use RPCharacterBot\Model\Channel;

/**
 * Static class with methods for threads.
 */
class ThreadHelper
{
    /**
     * Checks whether a thread is part of an RP channel - if so: it will join the thread.
     *
     * @param Thread $thread
     * @return void
     */
    public static function onNewThread(Thread $thread, Discord $client) {
        $channelId = $thread->parent->id;
        $guildId = $thread->parent->guild->id;
        
        if ($client->user->id == $thread->owner_id) {
            return;
        }

        Channel::fetchSingleByQuery(array(
                'id' => $channelId,
                'guild_id' => $guildId
            ), false
        )->then(
            function (?Channel $channel) use ($thread) {
                if (!is_null($channel)) {
                    if ($channel->getUseSubThreads()) {
                        $thread->join()->done();
                    }
                }
            }
        );
    }

    /**
     * Checks the threads of an existing channel and joins if required.
     *
     * @param DiscordChannel $channel
     * @return void
     */
    public static function onThreadedRpChannelCreated(DiscordChannel $channel, Discord $client) {
        $channel->threads->active()->then(function($threads) use ($client) {
            foreach ($threads as $thread) {
                /** @var Thread $thread */
                $thread->members->fetch($client->user->id)->then(function($arg) use($thread) {
                    if (!($arg instanceof Member)) { //Very inconvenient.
                        $thread->join()->done();
                    }                    
                });
            }
        });
    }
}