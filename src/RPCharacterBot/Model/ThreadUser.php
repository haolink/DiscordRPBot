<?php

namespace RPCharacterBot\Model;

use RPCharacterBot\Common\CharacterDefaultModel;
use RPCharacterBot\Common\DBQuery;

class ThreadUser extends CharacterDefaultModel
{
    protected static $CACHE_CONFIG = 'thread_users';
    protected static $CACHE_PRIORITY = 3;

    /**
     * Thread ID for this dataset.
     * (BigInt in DB).
     *
     * @var string
     */
    private $threadId;

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
        $this->threadId = $query['thread_id'];
        $this->userId = $query['user_id'];
    }

    /**
     * @inheritDoc
     */
    protected function fromDbResult(array $dbRow)
    {
        $this->threadId = $dbRow['thread_id'];
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
            'SELECT * FROM thread_users WHERE user_id = ? AND thread_id = ?', 
            array(
                $query['user_id'], $query['thread_id']
            ));
    }

    /**
     * @inheritDoc
     */
    protected function getInsertStatement() : DBQuery
    {
        return new DBQuery('
            INSERT INTO
                thread_users
                (thread_id, user_id, character_id, former_character_id)
            VALUES
                (?, ?, ?, ?)
        ', array(
            $this->threadId, $this->userId, $this->characterId, $this->formerCharacterId
        ));
    }

    /**
     * @inheritDoc
     */
    protected function getUpdateStatement() : DBQuery
    {
        return new DBQuery('
            UPDATE
            thread_users
            SET
                character_id = ?,
                former_character_id = ?
            WHERE
                thread_id = ? AND
                user_id = ?            
        ', array(
            $this->characterId, $this->formerCharacterId, $this->threadId, $this->userId
        ));
    }

    /**
     * @inheritDoc
     */
    protected function getDeleteStatement() : DBQuery
    {
        return new DBQuery('
            DELETE FROM
                thread_users
            WHERE
                thread_id = ? AND
                user_id = ?            
        ', array(
            $this->threadId, $this->userId
        ));
    }

    /**
     * Get (BigInt in DB).
     *
     * @return  string
     */ 
    public function getThreadId() : string
    {
        return $this->threadId;
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

    /**
     * Removes user data from the cache.
     *
     * @param string $id
     * @return void
     */
    public static function uncacheByUserId($id)
    {
        self::uncacheBySubQuery(array('user_id' => $id));
    }
}
