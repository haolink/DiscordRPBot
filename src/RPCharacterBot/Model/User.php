<?php

namespace RPCharacterBot\Model;

use RPCharacterBot\Common\BaseModel;
use RPCharacterBot\Common\DBQuery;

class User extends BaseModel
{
    protected static $CACHE_CONFIG = 'users';
    protected static $CACHE_PRIORITY = 1;

    /**
     * User ID (stored as string in PHP, as BIGINT in MariaDB).
     *
     * @var string
     */
    protected $id;

    /**
     * OOC prefix.
     *
     * @var string|null
     */
    protected $oocPrefix;

    /**
     * Returns the query to get one or multiple.
     *
     * @param array $query
     * @return DBQuery
     */
    protected static function getFetchQuery(array $query) : DBQuery
    {
        return new DBQuery(
            'SELECT * FROM users WHERE id = ?', 
            array(
                $query['id']
            ));
    }

    /**
     * Gets the query to insert this user into the database.
     *
     * @return DBQuery
     */
    protected function getInsertStatement() : DBQuery 
    {
        return new DBQuery(
            'INSERT INTO users
                (id, ooc_prefix)
            VALUES
                (?, ?)',
            array(
                $this->id,
                $this->oocPrefix
            ));
    }

    /**
     * Gets the query to delete this user from the database.
     *
     * @return DBQuery
     */
    protected function getDeleteStatement(): DBQuery
    {
        return new DBQuery(
            'DELETE FROM users WHERE id = ?', 
            array(
                $this->id
            ));
    }

    /**
     * Gets the query to update this user in the database.
     *
     * @return DBQuery
     */
    protected function getUpdateStatement(): DBQuery
    {
        return new DBQuery(
            'UPDATE users
                SET ooc_prefix = ?
            WHERE
                id = ?',
            array(
                $this->oocPrefix,
                $this->id
            ));
    }

    /**
     * Converts a DB result into a Channel info.
     *
     * @param array $dbRow
     * @return void
     */
    protected function fromDbResult(array $dbRow) 
    {
        $this->id = $dbRow['id'];
        $this->oocPrefix = $dbRow['ooc_prefix'];
    }

    /**
     * Creates a new object from a query.
     *
     * @param array $query
     * @return void
     */
    protected function createNewFromQuery(array $query)
    {
        $this->id = $query['id'];
        $this->oocPrefix = null;
    }

    /**
     * Get this user's OOC prefix.
     *
     * @return string|null
     */ 
    public function getOocPrefix() : ?string
    {
        return $this->oocPrefix;
    }

    /**
     * Sets this user's OOC prefix.
     *
     * @param string|null $oocPrefix
     *
     * @return  self
     */ 
    public function setOocPrefix(?string $oocPrefix)
    {
        $this->oocPrefix = $oocPrefix;
        $this->setUpdateState(self::DB_STATE_UPDATED);

        return $this;
    }

    /**
     * Get user ID (stored as string in PHP, as BIGINT in MariaDB).
     *
     * @return  string
     */ 
    public function getId() : string
    {
        return $this->id;
    }

    /**
     * Removes user data from the cache.
     *
     * @param string $id
     * @return void
     */
    public static function uncacheById($id)
    {
        self::uncacheBySubQuery(array('id' => $id));
    }
}
