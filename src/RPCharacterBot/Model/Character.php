<?php

namespace RPCharacterBot\Model;

use React\Promise\Deferred;
use React\Promise\ExtendedPromiseInterface;
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
     * Creates a new character for a user.
     *
     * @param string $userId
     * @param string $shortName
     * @param string $fullName
     * @return ExtendedPromiseInterface
     */
    public static function createNewCharacter(string $userId, string $shortName, string $fullName) : ExtendedPromiseInterface
    {
        $character = new Character();
        $character->userId = $userId;
        $character->characterShortname = $shortName;
        $character->characterName = $fullName;
        $character->dbState = self::DB_STATE_NEW;
        $character->defaultCharacter = false;
        
        self::addToCache(get_class($character), array('user_id' => $userId), $character);
        
        $deferred = new Deferred();

        /**
         * To query a character ID, it must immediately be deployed to the database.
         */
        $character->applyUpdate()->then(function() use ($character, $deferred) {
            return $deferred->resolve($character);
        });

        return $deferred->promise();
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
                ($this->defaultCharacter ? 1:0),
                $this->id
            ),                
        );
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
        $this->setUpdateState(self::DB_STATE_UPDATED);
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
        $this->setUpdateState(self::DB_STATE_UPDATED);
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
        $this->setUpdateState(self::DB_STATE_UPDATED);
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
        $this->setUpdateState(self::DB_STATE_UPDATED);
        $this->defaultCharacter = $defaultCharacter;

        return $this;
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
