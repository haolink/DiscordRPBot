<?php

namespace RPCharacterBot\Common;

use CharlotteDunois\Yasmin\Models\Message;

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
     * Sent messages to a webhook.
     *
     * @var array[]
     */
    private $webhookSubmissionCache;

    /**
     * When has the webhook cache been cleared last time?
     *
     * @var int
     */
    private $webhookCacheClearance;

    /**
     * Maximum age in seconds of an item in the webhook cache.
     *
     * @var int
     */
    private $webhookCacheAge;

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

        $this->webhookSubmissionCache = array();
        $this->webhookCacheClearance = time();
        $this->webhookCacheAge = 60;
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
            foreach($userCache as $channelId => &$message) {
                if (is_null($message)) {
                    $channelsToDelete[] = $channelId;
                    break;
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
     * Cleans old entries from the webhook cache.
     *
     * @return void
     */
    private function cleanWebhookCache()
    {
        $currentTime = time();
        if ($currentTime - $this->webhookCacheClearance < ($this->webhookCacheAge / 5)) {
            return;
        }

        $this->webhookCacheClearance = $currentTime;

        $idsToDelete = array();

        foreach ($this->webhookSubmissionCache as $id => $cacheItem) {
            if($currentTime - $cacheItem['time'] > $this->webhookCacheAge) {
                $idsToDelete[] = $id;
            }
        }

        if (count($idsToDelete) > 0) {
            foreach ($idsToDelete as $id) {
                unset($this->webhookSubmissionCache[$id]);
            }

            $this->webhookSubmissionCache = array_values($this->webhookSubmissionCache);
        }
    }

    /**
     * Stores a message in the webhook.
     *
     * @param string $userId
     * @param string $channelId
     * @param string $username
     * @param string $message
     * @return void
     */
    public static function submitToWebHook(string $userId, string $channelId, string $username, string $message)
    {
        $cache = self::getInstance();

        $currentTime = time();

        $cache->webhookSubmissionCache[] = array(
            'time' => $currentTime,
            'user_id' => $userId,
            'channel_id' => $channelId,
            'username' => $username,
            'message' => trim($message)
        );

        $cache->cleanWebhookCache();
    }

    /**
     * Tries to find a message by a user in the webhook cache.
     * If found it will be added to the main cache.
     *
     * @param Message $message
     * @return bool Do we have a cache hit?
     */
    public static function identifyBotMessage(Message $message) : bool
    {
        if (!$message->author->bot || ((int)($message->author->discriminator)) != 0) {
            return false;
        }

        $cache = self::getInstance();

        $webhookCacheHit = null;

        $username = $message->author->username;
        $content = $message->content;
        $channelId = $message->channel->id;

        foreach ($cache->webhookSubmissionCache as $cacheItem) {
            if ($cacheItem['channel_id'] != $channelId) {
                continue;
            }
            if ($cacheItem['username'] != $username) {
                continue;
            }

            $percent = 0;
            $matches = similar_text($content, $cacheItem['message'], $percent);

            if ($percent > 90) {
                $webhookCacheHit = $cacheItem;
                break;
            }
        }

        if (is_null($webhookCacheHit)) {
            return false;
        }

        $userId = $cacheItem['user_id'];
        if (!array_key_exists($userId, $cache->cachedData)) {
            $cache->cachedData[$userId] = array();
        }

        if (!array_key_exists($channelId, $cache->cachedData[$userId])) {
            $cache->cachedData[$userId] = null;
        }

        $cache->cachedData[$userId][$channelId] = $message;

        $cache->cleanMainCache();
        return true;
    }

    /**
     * Determins the last sent message by a user.
     *
     * @param string $userId
     * @param string $channelId
     * @return Message|null
     */
    public static function findLastUserMessage(string $userId, string $channelId) : ?Message
    {
        $cache = self::getInstance();
        $cache->cleanWebhookCache();

        if (!array_key_exists($userId, $cache->cachedData)) {
            return null;
        }

        if (!array_key_exists($channelId, $cache->cachedData[$userId])) {
            return null;
        }

        return $cache->cachedData[$userId][$channelId];
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
        $cache->cleanWebhookCache();

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
