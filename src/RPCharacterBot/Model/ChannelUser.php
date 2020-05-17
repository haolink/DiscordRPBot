<?php

namespace RPCharacterBot\Model;

use RPCharacterBot\Common\CharacterDefaultModel;
use RPCharacterBot\Common\DBQuery;

class ChannelUser extends CharacterDefaultModel
{
    protected static $CACHE_CONFIG = 'channel_users';
    protected static $CACHE_PRIORITY = 3;

    /**
     * Channel ID for this dataset.
     * (BigInt in DB).
     *
     * @var string
     */
    private $channelId;

    /**
     * User ID for this dataset.
     * (BigInt in DB).
     *
     * @var string
     */
    private $userId;

    /**
     * Character ID for this dataset.
     * (BigInt in DB).
     *
     * @var string|null
     */
    private $characterId;

    /**
     * Former character ID for this dataset.
     * (BigInt in DB).
     *
     * @var string|null
     */
    private $formerCharacterId;

    /**
     * @inheritDoc
     */
    protected function createNewFromQuery(array $query)
    {
        $this->channelId = $query['channel_id'];
        $this->userId = $query['user_id'];
    }

    /**
     * @inheritDoc
     */
    protected function fromDbResult(array $dbRow)
    {
        $this->channelId = $dbRow['channel_id'];
        $this->userId = $dbRow['user_id'];
        $this->characterId = $dbRow['character_id'];
        $this->formerCharacterId = $dbRow['former_character_id'];
    }

    /**
     * @inheritDoc
     */
    protected static function getFetchQuery(array $query) : DBQuery 
    {
        return new DBQuery(
            'SELECT * FROM channel_users WHERE user_id = ? AND channel_id = ?', 
            array(
                $query['user_id'], $query['channel_id']
            ));
    }

    /**
     * @inheritDoc
     */
    protected function getInsertStatement() : DBQuery
    {
        return new DBQuery('
            INSERT INTO
                channel_users
                (channel_id, user_id, character_id, former_character_id)
            VALUES
                (?, ?, ?)
        ', array(
            $this->channelId, $this->userId, $this->characterId, $this->formerCharacterId
        ));
    }

    /**
     * @inheritDoc
     */
    protected function getUpdateStatement() : DBQuery
    {
        return new DBQuery('
            UPDATE
                channel_users
            SET
                character_id = ?,
                former_character_id = ?
            WHERE
                channel_id = ? AND
                user_id = ?            
        ', array(
            $this->characterId, $this->formerCharacterId, $this->channelId, $this->userId
        ));
    }

    /**
     * @inheritDoc
     */
    protected function getDeleteStatement() : DBQuery
    {
        return new DBQuery('
            DELETE FROM
                channel_users
            WHERE
                channel_id = ? AND
                user_id = ?            
        ', array(
            $this->channelId, $this->userId
        ));
    }

    /**
     * Get (BigInt in DB).
     *
     * @return  string
     */ 
    public function getChannelId() : string
    {
        return $this->channelId;
    }

    /**
     * Get (BigInt in DB).
     *
     * @return  string
     */ 
    public function getUserId() : string
    {
        return $this->userId;
    }

    /**
     * @inheritDoc
     */
    public function getDefaultCharacterId(): ?string
    {
        return $this->characterId;
    }

    /**
     * @inheritDoc
     */
    public function setDefaultCharacterId(?string $characterId): void
    {
        $this->setUpdateState(self::DB_STATE_UPDATED);
        $this->characterId = $characterId;
    }

    /**
     * @inheritDoc
     */
    public function getFormerCharacterId() : ?string
    {
        return $this->formerCharacterId;
    }

    /**
     * @inheritDoc
     */
    public function setFormerCharacterId(?string $formerCharacterId)
    {
        $this->setUpdateState(self::DB_STATE_UPDATED);
        $this->formerCharacterId = $formerCharacterId;
    }
}
