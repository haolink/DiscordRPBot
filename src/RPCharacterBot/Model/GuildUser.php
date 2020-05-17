<?php

namespace RPCharacterBot\Model;

use RPCharacterBot\Common\CharacterDefaultModel;
use RPCharacterBot\Common\DBQuery;

class GuildUser extends CharacterDefaultModel
{
    protected static $CACHE_CONFIG = 'guild_users';
    protected static $CACHE_PRIORITY = 3;

    /**
     * Guild ID for this dataset.
     * (BigInt in DB).
     *
     * @var string
     */
    private $guildId;

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
        $this->guildId = $query['guild_id'];
        $this->userId = $query['user_id'];
    }

    /**
     * @inheritDoc
     */
    protected function fromDbResult(array $dbRow)
    {
        $this->guildId = $dbRow['guild_id'];
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
            'SELECT * FROM guild_users WHERE user_id = ? AND guild_id = ?', 
            array(
                $query['user_id'], $query['guild_id']
            ));
    }

    /**
     * @inheritDoc
     */
    protected function getInsertStatement() : DBQuery
    {
        return new DBQuery('
            INSERT INTO
                guild_users
                (guild_id, user_id, character_id, former_character_id)
            VALUES
                (?, ?, ?)
        ', array(
            $this->guildId, $this->userId, $this->characterId, $this->formerCharacterId
        ));
    }

    /**
     * @inheritDoc
     */
    protected function getUpdateStatement() : DBQuery
    {
        return new DBQuery('
            UPDATE
                guild_users
            SET
                character_id = ?,
                former_character_id = ?
            WHERE
                guild_id = ? AND
                user_id = ?            
        ', array(
            $this->characterId, $this->formerCharacterId, $this->guildId, $this->userId
        ));
    }

    /**
     * @inheritDoc
     */
    protected function getDeleteStatement() : DBQuery
    {
        return new DBQuery('
            DELETE FROM
                guild_users
            WHERE
                guild_id = ? AND
                user_id = ?            
        ', array(
            $this->guildId, $this->userId
        ));
    }

    /**
     * Get (BigInt in DB).
     *
     * @return  string
     */ 
    public function getGuildId() : string
    {
        return $this->guildId;
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
