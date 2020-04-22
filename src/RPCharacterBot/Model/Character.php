<?php

namespace RPCharacterBot\Model;

use RPCharacterBot\Common\BaseModel;
use RPCharacterBot\Common\DBQuery;

class Character extends BaseModel
{
    protected static $CACHE_CONFIG = 'characters';
    protected static $CACHE_PRIORITY = 2;

    /**
     * Character ID (stored as string in PHP, as BIGINT in MariaDB).
     *
     * @var string
     */
    protected $id;

    /**
     * Character ID (stored as string in PHP, as BIGINT in MariaDB).
     *
     * @var string
     */
    protected $userId;

    /**
     * Character shortname.
     *
     * @var string
     */
    protected $characterShortname;

    /**
     * Character name.
     *
     * @var string
     */
    protected $characterName;

    /**
     * Character avatar.
     *
     * @var string|null
     */
    protected $characterAvatar;

    /**
     * Default character.
     *
     * @var boolean
     */
    protected $defaultCharacter;

    /**
     * Returns the query to get one or multiple.
     *
     * @param array $query
     * @return DBQuery
     */
    protected static function getFetchQuery(array $query) : DBQuery 
    {
        return new DBQuery(
            'SELECT * FROM characters WHERE user_id = ?', 
            array(
                $query['user_id']
            ));
    }

    /**
     * Gets the query to insert this character into the database.
     *
     * @return DBQuery
     */
    protected function getInsertStatement() : DBQuery 
    {
        return new DBQuery(
            'INSERT INTO characters
                (user_id, character_shortname, character_name, character_avatar, default_character)
            VALUES
                (?, ?, ?, ?, ?)',
            array(
                $this->userId,
                $this->characterShortname,
                $this->characterName,
                $this->characterAvatar,
                ($this->defaultCharacter ? 1:0)
            ));
    }

    /**
     * Sets the character id after inserting.
     *
     * @param int $insertId
     * @return void
     */
    protected function setObjectIdAfterInsert($insertId)
    {
        $this->id = $insertId;
    }

    /**
     * Gets the query to delete this character from the database.
     *
     * @return DBQuery
     */
    protected function getDeleteStatement(): DBQuery
    {
        return new DBQuery(
            'DELETE FROM characters WHERE id = ?', 
            array(
                $this->id
            ));
    }

    /**
     * Gets the query to update this character in the database.
     *
     * @return DBQuery
     */
    protected function getUpdateStatement(): DBQuery
    {
        return new DBQuery(
            'UPDATE characters
                SET character_shortname = ?,
                    character_name = ?,
                    character_avatar = ?,
                    default_character= ?
            WHERE
                id = ?',
            array(
                $this->characterShortname,
                $this->characterName,
                $this->characterAvatar,
                ($this->defaultCharacter ? 1:0)
            ));
    }

    /**
     * Converts a DB result into a Character info.
     *
     * @param array $dbRow
     * @return void
     */
    protected function fromDbResult(array $dbRow) 
    {
        $this->id = $dbRow['id'];
        $this->userId = $dbRow['user_id'];
        $this->characterShortname = $dbRow['character_shortname'];
        $this->characterName = $dbRow['character_name'];
        $this->characterAvatar = $dbRow['character_avatar'];
        $this->defaultCharacter = ($dbRow['default_character'] != 0);
    }

    /**
     * Creates a new object from a query.
     *
     * @param array $query
     * @return void
     */
    protected function createNewFromQuery(array $query)
    {
        //Not required
    }    

    /**
     * Get character ID (stored as string in PHP, as BIGINT in MariaDB).
     *
     * @return  string
     */ 
    public function getId() : string
    {
        return $this->id;
    }

    /**
     * Get character ID (stored as string in PHP, as BIGINT in MariaDB).
     *
     * @return  string
     */ 
    public function getUserId() : string
    {
        return $this->userId;
    }

    /**
     * Get character shortname.
     *
     * @return  string|null
     */ 
    public function getCharacterShortname() : ?string
    {
        return $this->characterShortname;
    }

    /**
     * Set character shortname.
     *
     * @param  string|null  $characterShortname  Character shortname.
     *
     * @return  self
     */ 
    public function setCharacterShortname(?string $characterShortname)
    {
        $this->characterShortname = $characterShortname;

        return $this;
    }

    /**
     * Get character name.
     *
     * @return  string
     */ 
    public function getCharacterName() : ?string
    {
        return $this->characterName;
    }

    /**
     * Set character name.
     *
     * @param  string|null  $characterName  Character name.
     *
     * @return  self
     */ 
    public function setCharacterName(?string $characterName)
    {
        $this->characterName = $characterName;

        return $this;
    }

    /**
     * Get character avatar.
     *
     * @return  string|null
     */ 
    public function getCharacterAvatar() : ?string
    {
        return $this->characterAvatar;
    }

    /**
     * Set character avatar.
     *
     * @param  string|null  $characterAvatar  Character avatar.
     *
     * @return  self
     */ 
    public function setCharacterAvatar(?string $characterAvatar)
    {
        $this->characterAvatar = $characterAvatar;

        return $this;
    }

    /**
     * Get default character.
     *
     * @return  boolean
     */ 
    public function getDefaultCharacter() : bool
    {
        return $this->defaultCharacter;
    }

    /**
     * Set default character.
     *
     * @param  boolean  $defaultCharacter  Default character.
     *
     * @return  self
     */ 
    public function setDefaultCharacter(bool $defaultCharacter)
    {
        $this->defaultCharacter = $defaultCharacter;

        return $this;
    }
}
