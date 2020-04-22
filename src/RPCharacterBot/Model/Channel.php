<?php

namespace RPCharacterBot\Model;

use CharlotteDunois\Yasmin\Models\Webhook;
use RPCharacterBot\Common\BaseModel;
use RPCharacterBot\Common\DBQuery;

class Channel extends BaseModel
{
    protected static $CACHE_CONFIG = 'channels';
    protected static $CACHE_PRIORITY = 1;

    /**
     * Channel ID (stored as string in PHP, as BIGINT in MariaDB).
     *
     * @var string
     */
    protected $id;

    /**
     * Webhook ID.
     *
     * @var string
     */
    protected $webhookId;

    /**
     * Does this channel allow OOC talk.
     *
     * @var boolean
     */
    protected $allowOoc = true;

    /**
     * Webhook object.
     *
     * @var Webhook
     */
    protected $webhook;

    /**
     * Returns the query to get one or multiple.
     *
     * @param array $query
     * @return DBQuery
     */
    protected static function getFetchQuery(array $query) : DBQuery 
    {
        return new DBQuery(
            'SELECT * FROM channels WHERE id = ?', 
            array(
                $query['id']
            ));
    }

    /**
     * Gets the query to insert this channel into the database.
     *
     * @return DBQuery
     */
    protected function getInsertStatement() : DBQuery 
    {
        return new DBQuery(
            'INSERT INTO guilds
                (id, webhook_id, allow_ooc)
            VALUES
                (?, ?, ?)',
            array(
                $this->id,
                $this->webhookId,
                $this->allowOoc ? 1:0
            ));
    }

    /**
     * Gets the query to delete this channel from the database.
     *
     * @return DBQuery
     */
    protected function getDeleteStatement(): DBQuery
    {
        return new DBQuery(
            'DELETE FROM channels WHERE id = ?', 
            array(
                $this->id
            ));
    }

    /**
     * Gets the query to update this channel in the database.
     *
     * @return DBQuery
     */
    protected function getUpdateStatement(): DBQuery
    {
        return new DBQuery(
            'UPDATE guilds
                SET webhook_id = ?,
                    allow_ooc = ?
            WHERE
                id = ?',
            array(
                ($this->allowOoc ? 1:0),                
                $this->webhookId,
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
        $this->webhookId = $dbRow['webhook_id'];
        $this->allowOoc = ($dbRow['allow_ooc'] != 0);
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
        $this->webhookId = null; //Will cause a saving error!
        $this->allowOoc = true;
    }

    /**
     * Get does this channel allow OOC talk.
     *
     * @return  boolean
     */ 
    public function getAllowOoc()
    {
        return $this->allowOoc;
    }

    /**
     * Set does this channel allow OOC talk.
     *
     * @param  bool  $allowOoc  Does this channel allow OOC talk.
     *
     * @return  self
     */ 
    public function setAllowOoc(bool $allowOoc)
    {
        $this->allowOoc = $allowOoc;
        $this->setUpdateState(self::DB_STATE_UPDATED);

        return $this;
    }

    /**
     * Get channel ID (stored as string in PHP, as BIGINT in MariaDB).
     *
     * @return  string
     */ 
    public function getId() : string
    {
        return $this->id;
    }

    /**
     * Get webhook ID.
     *
     * @return ?string
     */ 
    public function getWebhookId() : ?string
    {
        return $this->webhookId;
    }

    /**
     * Set webhook Id.
     *
     * @param string  $webhookId  Webhook ID.
     *
     * @return self
     */ 
    public function setWebhookId(string $webhookId)
    {
        $this->webhookId = $webhookId;

        return $this;
    }

    /**
     * Get webhook object.
     *
     * @return  Webhook
     */ 
    public function getWebhook()
    {
        return $this->webhook;
    }

    /**
     * Set webhook object.
     *
     * @param  Webhook  $webhook  Webhook object.
     *
     * @return  self
     */ 
    public function setWebhook(Webhook $webhook)
    {
        $this->webhook = $webhook;

        return $this;
    }
}
