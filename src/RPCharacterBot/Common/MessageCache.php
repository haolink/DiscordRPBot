<?php

namespace RPCharacterBot\Common;

use Discord\Parts\Channel\Message;
use Discord\Parts\Thread\Thread;

class MessageCache
{
    /**
     * Message cache.
     *
     * @var array
     */
    private $cachedData;

    /**
     * Last time the cache was cleared.
     *
     * @var int
     */
    private $lastCacheClearance;

    /**
     * Maximum age of an item in the message cache.
     *
     * @var int
     */
    private $maxCacheAge;

    /**
     * Cache object.
     *
     * @var MessageCache
     */
    private static $instance;

    /**
     * Constructor.
     */
    private function __construct() 
    {
        $this->cachedData = array();
        $this->lastCacheClearance = time();
        $this->maxCacheAge = 600;
    }

    /**
     * Returns the message cache.
     *
     * @return MessageCache
     */
    private static function getInstance() : MessageCache
    {
        if (!isset(self::$instance)) {
            $cache = new MessageCache();
            self::$instance = $cache;            
        }

        return self::$instance;
    }

    /**
     * Stores message results from the webhook.
     *
     * @param string $userId
     * @param string $channelId
     * @param Message[] $messages
     * @return void
     */
    public static function submitToCache($userId, $channelId, $messages) {
        $cache = self::getInstance();
        $cache->cleanMainCache();

        if (!array_key_exists($userId, $cache->cachedData)) {
            $cache->cachedData[$userId] = array();
        }

        $cache->cachedData[$userId][$channelId] = $messages;
    }

    /**
     * Cleans the main message cache.
     *
     * @return void
     */
    private function cleanMainCache()
    {
        $currentTime = time();
        if ($currentTime - $this->lastCacheClearance < ($this->maxCacheAge / 5)) {
            return;
        }

        $this->lastCacheClearance = $currentTime;

        $usersToDelete = array();
        foreach ($this->cachedData as $userId => &$userCache) {
            $channelsToDelete = array();
            foreach($userCache as $channelId => &$messages) {
                if (is_null($messages)) {
                    $channelsToDelete[] = $channelId;
                    break;
                }

                if (is_array($messages)) {
                    $message = $messages[0];
                } else {
                    $message = $messages;
                }

                /** @var Message $message */
                if ($currentTime - $message->createdTimestamp > $this->maxCacheAge) {
                    $channelsToDelete[] = $channelId;
                }
            }
            unset($message);

            foreach ($channelsToDelete as $channelId) {
                unset($userCache[$channelId]);
            }

            if (count($userCache) == 0) {
                $usersToDelete[] = $userId;
            }
        }
        unset($userCache);

        foreach ($usersToDelete as $userId) {
            unset ($this->cachedData[$userId]);
        }
    }

    /**
     * Determins the last sent message by a user.
     *
     * @param string $userId
     * @param string $channelId
     * @return Message[]|null
     */
    public static function findLastUserMessages(string $userId, string $channelId) : ?array
    {
        $cache = self::getInstance();
        $cache->cleanMainCache();

        if (!array_key_exists($userId, $cache->cachedData)) {
            return null;
        }

        if (!array_key_exists($channelId, $cache->cachedData[$userId])) {
            return null;
        }

        $data = $cache->cachedData[$userId][$channelId];

        if ($data instanceof Message) {
            $data = [$data];
        }

        if (count ($data) == 0) {
            return null;
        }

        return $data;
    }

    /**
     * Removes a message from cache.
     *
     * @param string $userId
     * @param string $channelId
     * @return void
     */
    public static function removeFromCache(string $userId, string $channelId)
    {
        $cache = self::getInstance();
        $cache->cleanMainCache();

        if (!array_key_exists($userId, $cache->cachedData)) {
            return;
        }

        if (!array_key_exists($channelId, $cache->cachedData[$userId])) {
            return;
        }

        unset($cache->cachedData[$userId][$channelId]);

        if (count($cache->cachedData[$userId]) == 0) {
            unset($cache->cachedData[$userId]);
        }
    }
}
