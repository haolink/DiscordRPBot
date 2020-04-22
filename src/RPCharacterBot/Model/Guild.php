<?php

namespace RPCharacterBot\Model;

use React\MySQL\QueryResult;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use RPCharacterBot\Common\BaseModel;
use RPCharacterBot\Common\DBQuery;

class Guild extends BaseModel
{
    protected static $CACHE_CONFIG = 'guilds';
    protected static $CACHE_PRIORITY = 1;

    const RPCHAR_SETTING_CHANNEL = 1;
    const RPCHAR_SETTING_GUILD = 2;

    /**
     * Guild ID (stored as string in PHP, as BIGINT in MariaDB).
     *
     * @var string
     */
    protected $id;

    /**
     * RP character selection setting.
     *
     * @var int
     */
    protected $rpCharacterSetting = self::RPCHAR_SETTING_CHANNEL;

    /**
     * Message prefix.
     *
     * @var string|null
     */
    protected $mainPrefix;

    /**
     * Quick command prefix within RP rooms.
     *
     * @var string|null
     */
    protected $quickPrefix;

    /**
     * Returns the query to get one or multiple.
     *
     * @param array $query
     * @return DBQuery
     */
    protected static function getFetchQuery(array $query) : DBQuery 
    {
        return new DBQuery(
            'SELECT * FROM guilds WHERE id = ?', 
            array(
                $query['id']
            ));
    }

    /**
     * Gets the query to insert this guild into the database.
     *
     * @return DBQuery
     */
    protected function getInsertStatement() : DBQuery 
    {
        return new DBQuery(
            'INSERT INTO guilds
                (id, rpcharsetting, main_prefix, quick_prefix)
            VALUES
                (?, ?, ?, ?)',
            array(
                $this->id,
                $this->rpCharacterSetting,
                $this->mainPrefix,
                $this->quickPrefix            
            ));
    }

    /**
     * Gets the query to delete this guild from the database.
     *
     * @return DBQuery
     */
    protected function getDeleteStatement(): DBQuery
    {
        return new DBQuery(
            'DELETE FROM guilds WHERE id = ?', 
            array(
                $this->id
            ));
    }

    /**
     * Gets the query to update this guild in the database.
     *
     * @return DBQuery
     */
    protected function getUpdateStatement(): DBQuery
    {
        return new DBQuery(
            'UPDATE guilds
                SET rpcharsetting = ?,
                    main_prefix = ?,
                    quick_prefix = ?
            WHERE
                id = ?',
            array(
                $this->rpCharacterSetting,
                $this->mainPrefix,
                $this->quickPrefix,
                $this->id
            ));
    }

    /**
     * Converts a DB result into a Guild info.
     *
     * @param array $dbRow
     * @return void
     */
    protected function fromDbResult(array $dbRow) 
    {
        $this->id = $dbRow['id'];
        $this->rpCharacterSetting = $dbRow['rpcharsetting'];
        $this->mainPrefix = $dbRow['main_prefix'];
        $this->quickPrefix = $dbRow['quick_prefix'];
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
        $this->rpCharacterSetting = self::RPCHAR_SETTING_CHANNEL;
    }

    /**
     * Get guild ID.
     *
     * @return  string
     */ 
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get rP character selection setting.
     *
     * @return  int
     */ 
    public function getRpCharacterSetting()
    {
        return $this->rpCharacterSetting;
    }

    /**
     * Set RP character selection setting.
     *
     * @param  int  $rpCharacterSetting  RP character selection setting.
     *
     * @return  self
     */ 
    public function setRpCharacterSetting(int $rpCharacterSetting)
    {
        $this->rpCharacterSetting = $rpCharacterSetting;
        $this->setUpdateState(self::DB_STATE_UPDATED);

        return $this;
    }

    /**
     * Get message prefix.
     *
     * @return  string|null
     */ 
    public function getMainPrefix() : ?string
    {
        return $this->mainPrefix;
    }

    /**
     * Set message prefix.
     *
     * @param  string|null  $mainPrefix  Message prefix.
     *
     * @return  self
     */ 
    public function setMainPrefix(?string $mainPrefix)
    {
        $this->mainPrefix = $mainPrefix;
        $this->setUpdateState(self::DB_STATE_UPDATED);

        return $this;
    }

    /**
     * Get quick command prefix within RP rooms.
     *
     * @return  string|null
     */ 
    public function getQuickPrefix() : ?string
    {
        return $this->quickPrefix;
    }

    /**
     * Set quick command prefix within RP rooms.
     *
     * @param  string|null  $quickPrefix  Quick command prefix within RP rooms.
     *
     * @return  self
     */ 
    public function setQuickPrefix(?string $quickPrefix)
    {
        $this->quickPrefix = $quickPrefix;
        $this->setUpdateState(self::DB_STATE_UPDATED);

        return $this;
    }
}
