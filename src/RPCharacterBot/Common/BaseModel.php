<?php

namespace RPCharacterBot\Common;

use Closure;
use React\EventLoop\LoopInterface;
use React\MySQL\Io\LazyConnection;
use React\MySQL\QueryResult;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use RPCharacterBot\Bot\Bot;
use RPCharacterBot\Common\Cache\ModelCache;
use RPCharacterBot\Common\Cache\UpdateModel;
use RPCharacterBot\Exception\BotException;

abstract class BaseModel
{
    const DB_STATE_CURRENT = 0;
    const DB_STATE_NEW = 1;
    const DB_STATE_UPDATED = 2;
    const DB_STATE_DELETED = 3;

    /**
     * Cache for all objects.
     *
     * @var ModelCache[]
     */
    private static $cache = array();

    /**
     * Is the object new or requires to be updated in the database.
     *
     * @var int
     */
    protected $dbState;

    /**
     * Bot object.
     *
     * @var Bot
     */
    protected $bot;

    /**
     * Event loop.
     *
     * @var LoopInterface
     */
    protected $loop;

    /**
     * Database object.
     *
     * @var LazyConnection
     */
    protected $db;

    /**
     * Creator.
     *
     * @param int $cacheSize
     * @param string $id
     */
    private function __construct() 
    {
        $this->bot = Bot::getInstance();
        $this->db = $this->bot->getDbConnection();
        $this->loop = $this->bot->getLoop();        
        $this->dbState = self::DB_STATE_CURRENT;
    }

    /**
     * Fetches a single object using a query.
     *
     * @param string|array $id
     * @param bool $createNew
     * @return PromiseInterface
     */
    public static function fetchSingleByQuery(array $query, bool $createNew = true) : PromiseInterface
    {
        $deferred = new Deferred();

        self::fetchByQuery($query, $createNew)->then(function(array $results) use($deferred) {
            if(count($results) == 0) {
                $deferred->resolve(null);
            } else {
                $deferred->resolve($results[0]);
            }
        });

        return $deferred->promise();
    }

    /**
     * Fetches objects using a query.
     *
     * @param string|array $id
     * @return PromiseInterface
     */
    public static function fetchByQuery(array $query, bool $createNew = true) : PromiseInterface
    {
        $deferred = new Deferred();

        if(is_string($query)) {
            $query = array(
                'id' => $query
            );
        }

        $loop = Bot::getInstance()->getLoop();
        $class = get_called_class();

        $loop->futureTick(function () use ($query, $deferred, $class, $createNew) {
            self::fetchByQueryInternal($class, $deferred, $query, $createNew);
        });

        return $deferred->promise();
    }

    /**
     * Fetches an object by its ID internally.
     *
     * @param string $class
     * @param Deferred $deferred
     * @param array $query
     * @return void
     */
    private static function fetchByQueryInternal(string $class, Deferred $deferred, array $query, bool $createNew)
    {
        $bot = Bot::getInstance();            
        $db = $bot->getDbConnection();

        if(!array_key_exists($class, self::$cache)) {
            if(count(self::$cache) == 0) {
                $bot->getLoop()->addPeriodicTimer(15, function() {
                    self::storeCachedObjects();
                });
            }

            $cacheSize = 100;
            $cachePriority = 1;
            
            if(property_exists($class, 'CACHE_CONFIG')) {
                $cacheConfig = $class::$CACHE_CONFIG;
                $config = $bot->getConfig();
                if(array_key_exists('caches', $config) && array_key_exists($cacheConfig, $config['caches'])) {
                    $cacheSize = $config['caches'][$cacheConfig];
                }
            }
            if(property_exists($class, 'CACHE_PRIORITY')) {
                $cachePriority = $class::$CACHE_PRIORITY;
            }

            self::$cache[$class] = new ModelCache($cacheSize, $cachePriority);
            //echo $class . ' - ' . $cacheSize . ' - ' .$cachePriority . PHP_EOL;
        }

        $modelCache = self::$cache[$class];
        $objects = $modelCache->getObjectsByQuery($query);

        if(is_null($objects)) {
            /** @var DBQuery $dbQuery */
            $dbQuery = $class::getFetchQuery($query);
            $db->query($dbQuery->getSql(), $dbQuery->getParameters())->then(
                function(QueryResult $queryResult) use($class, $query, $modelCache, $deferred, $createNew) {
                    $rows = $queryResult->resultRows;
                    $results = array();
                    if(count($rows) > 0) {
                        foreach($rows as $row) {
                            /** @var BaseModel $object */
                            $object = new $class();
                            $object->fromDbResult($row);
                            $results[] = $object;
                        }
                    } elseif($createNew) {
                        /** @var BaseModel $newObject */
                        $newObject = new $class();
                        $newObject->createNewFromQuery($query);
                        $newObject->dbState = self::DB_STATE_NEW;
                        $results[] = $newObject;
                    }

                    if(count($results) > 0) {
                        $modelCache->addModelsToCache($query, $results);
                    }

                    $deferred->resolve($results);
                }
            )->otherwise(function($error) {
                throw new BotException($error);
            });
        } else {
            //echo 'Cache match ';
            $deferred->resolve($objects);
        }        
    }

    /**
     * Forces an object into the database before it's getting uncached.
     *
     * @return void
     */
    public function forceSaveToDb()
    {
        $this->applyUpdate()->done();
    }

    /**
     * Applies an update to a model.
     *
     * @return void
     */
    protected function applyUpdate() : PromiseInterface
    {
        if($this->dbState == self::DB_STATE_DELETED) {
            return $this->performDelete();
        } if($this->dbState == self::DB_STATE_UPDATED) {
            return $this->performUpdate();
        } elseif($this->dbState == self::DB_STATE_NEW) {
            return $this->performInsert();
        }
        return (new Deferred())->promise();
    }

    /**
     * Inserts a model into the database.
     *
     * @return PromiseInterface
     */
    protected function performInsert() : PromiseInterface
    {
        $deferred = new Deferred();

        $insertStmt = $this->getInsertStatement();
        $that = $this;

        $this->db->query($insertStmt->getSql(), $insertStmt->getParameters())->then(function(QueryResult $result) use ($deferred, $that) {
            $that->setObjectIdAfterInsert($result->insertId);
            $that->dbState = self::DB_STATE_CURRENT;
            $deferred->resolve();
        })->otherwise(function($error) {
            $msg = $error;
            if($error instanceof \Exception) {
                $msg = $error->getMessage();
            }
            Bot::getInstance()->writeln('Error: ' . $msg);
        });

        return $deferred->promise();
    }

    /**
     * Might be required to be overwritten by model.
     *
     * @param mixed $insertId
     * @return void
     */
    protected function setObjectIdAfterInsert($insertId)
    {
        return;
    }

    /**
     * Deletes a model from the database and forces deletion from cache.
     *
     * @return PromiseInterface
     */
    protected function performDelete() : PromiseInterface
    {
        $deferred = new Deferred();

        $delQuery = $this->getDeleteStatement();
        $that = $this;

        $this->db->query($delQuery->getSql(), $delQuery->getParameters())->then(function(QueryResult $result) use ($that, $deferred) {
            $that->forceRemoveFromCache();

            $deferred->resolve();
        });

        return $deferred->promise();
    }

    /**
     * Updates a model in the database.
     *
     * @return PromiseInterface
     */
    protected function performUpdate() : PromiseInterface
    {
        $deferred = new Deferred();

        $updateStmt = $this->getUpdateStatement();
        $that = $this;

        $this->db->query($updateStmt->getSql(), $updateStmt->getParameters())->then(function(QueryResult $result) use ($deferred, $that) {
            $that->dbState = self::DB_STATE_CURRENT;
            $deferred->resolve();
        });

        return $deferred->promise();
    }
    
    /**
     * Sets the new database update state.
     *
     * @param integer $newState
     * @return void
     */
    protected function setUpdateState(int $newState) 
    {
        if($this->dbState == self::DB_STATE_CURRENT) {
            $this->dbState = $newState;
        }
    }

    /**
     * Caching ready.
     *
     * @return void
     */
    private static function storeCachedObjects() 
    {
        /** @var UpdateModel[] $queuedUpdates */
        $queuedUpdates = array();

        foreach(self::$cache as $class => $modelCache) { 
            $modelCache->collectRequiredUpdates($queuedUpdates);
        }

        foreach($queuedUpdates as $queuedUpdate) {
            $model = $queuedUpdate->getModel();

            $model->applyUpdate()->done();
        }
    }

    /**
     * Removes objects from the cache depending on the query.
     *
     * @param array $subQuery
     * @return void
     */
    protected static function uncacheBySubQuery(array $subQuery) 
    {
        $calledClass = get_called_class();
        if(!array_key_exists($calledClass, self::$cache)) {
            return;
        }
        self::$cache[$calledClass]->uncache($subQuery);
    }

    /**
     * Marks an object for deletion.
     */
    public function delete() {
        $this->dbState = self::DB_STATE_DELETED;        
    }

    /**
     * Forces removal of this object from cache.
     *
     * @return void
     */
    protected function forceRemoveFromCache()
    {
        self::$cache[get_class($this)]->delete($this);
    }
    
    /**
     * Fills data using a query.
     *
     * @param array $query
     * @return void
     */
    protected abstract function createNewFromQuery(array $query);

    /**
     * Takes data from a database result.
     *
     * @param array $query
     * @return void
     */
    protected abstract function fromDbResult(array $dbRow);

    /**
     * Inserts the object into the database.
     *
     * @return PromiseInterface
     */
    abstract protected function getInsertStatement() : DBQuery;

    /**
     * Updates the object in the database.
     *
     * @return PromiseInterface
     */
    abstract protected function getUpdateStatement() : DBQuery;

    /**
     * Deletes the object from the database.
     *
     * @return DBQuery
     */
    abstract protected function getDeleteStatement() : DBQuery;

    /**
     * Get is the object new or requires to be updated in the database.
     *
     * @return  int
     */ 
    public function getDbState()
    {
        return $this->dbState;
    }
}
