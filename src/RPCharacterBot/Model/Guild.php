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
     * Guild ID.
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
                (id, rpcharsetting)
            VALUES
                (?, ?)',
            array(
                $this->id,
                $this->rpCharacterSetting
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
                SET rpcharsetting = ?
            WHERE
                id = ?',
            array(
                $this->rpCharacterSetting,
                $this->id
            ));
    }
    
    /**
     * Uncache object.
     *
     * @return void
     */
    public function uncache() 
    {
        self::uncacheBySubQuery(array('id' => $this->id));
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
}
