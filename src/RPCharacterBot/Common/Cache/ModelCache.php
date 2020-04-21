<?php

namespace RPCharacterBot\Common\Cache;

use CharlotteDunois\Yasmin\Models\Base;
use RPCharacterBot\Common\BaseModel;

class ModelCache
{
    /**
     * How big is this cache?
     *
     * @var int
     */
    private $cacheSize;

    /**
     * Which cache needs to be flushed first (the higher, the later).
     *
     * @var int
     */
    private $cachePriority;

    /**
     * Objects in that cache.
     *
     * @var CacheGroup[]
     */
    private $cachedObjectGroups;

    /**
     * Objects which are to be deleted from the cache.
     *
     * @var BaseModel[]
     */
    private $uncachedObjects;

    /**
     * Creates a new object cache.
     *
     * @param int $cacheSize
     */
    public function __construct($cacheSize, $cachePriority)
    {
        $this->cacheSize = $cacheSize;
        $this->cachePriority = $cachePriority;
        $this->cachedObjectGroups = array();
        $this->uncachedObjects = array();
    }

    /**
     * Converts a query array to a string.
     *
     * @param array $query
     * @return string
     */
    private function queryArrayToString(array $query) : string {
        return json_encode($query);
    }

    /**
     * Converts a query string to an array.
     *
     * @param array $query
     * @return string
     */
    private function queryStringToArray(string $queryString) : array {
        return json_decode($queryString, true);
    }

    /**
     * Searches an object group by its id. If found the object group will be placed
     * at the top of the index.
     *
     * @param string $id
     * @return BaseModel[]|null
     */
    public function getObjectsByQuery(array $query) : ?array
    {
        $queryString = $this->queryArrayToString($query);

        $i = 0;
        $foundObjectGroup = null;
        $foundObjects = null;
        foreach ($this->cachedObjectGroups as $cachedObjectGroup) {
            if($cachedObjectGroup->getQueryString() == $queryString) {
                $foundObjectGroup = $cachedObjectGroup;
                break;
            }
            $i++;
        }
        
        if(!is_null($foundObjectGroup)) {
            unset($this->cachedObjectGroups[$i]);
            array_unshift($this->cachedObjectGroups, $cachedObjectGroup);

            $foundObjectArray = array();
            foreach($foundObjectGroup->getCachedObjects() as $object) {
                if($object->getDbState() != BaseModel::DB_STATE_DELETED) {
                    $foundObjectArray[] = $object;
                }
            }
            if(count($foundObjectArray) > 0) {
                $foundObjects = $foundObjectArray;
            }
        }

        return $foundObjects;
    }


    /**
     * Adds models to the cache by their query.
     *
     * @param array $query
     * @param BaseModel[] $baseModel
     */
    public function addModelsToCache(array $query, array $models)
    {
        $queryString = $this->queryArrayToString($query);
        $ret = null;

        $cacheGroup = new CacheGroup($queryString, $models);

        $this->cachedObjectGroups[] = $cacheGroup;

        if(count($this->cachedObjectGroups) > $this->cacheSize) {
            $droppedObjectGroup = $this->cachedObjectGroups[$this->cacheSize];
            unset($this->cachedObjectGroups[$this->cacheSize]);

            $droppedObjects = $droppedObjectGroup->getCachedObjects();
            foreach($droppedObjects as $droppedObject) {
                $this->uncachedObjects[] = $droppedObject;
            }
        }
        return $ret;
    }

    /**
     * Adds all waiting updates into the update queue.
     *
     * @return void
     */
    public function collectRequiredUpdates(&$queuedUpdates)
    {
        foreach($this->cachedObjectGroups as $group) {
            foreach($group->getCachedObjects() as $object) {
                if($object->getDbState() != BaseModel::DB_STATE_CURRENT) {
                    $queuedUpdates[] = new UpdateModel(
                        $object, $this->cachePriority
                    );
                }
            }
        }

        foreach($this->uncachedObjects as $object) {
            if($object->getDbState() != BaseModel::DB_STATE_CURRENT) {
                $queuedUpdates[] = new UpdateModel(
                    $object, $this->cachePriority
                );
            }
        }

        $this->uncachedObjects = array();
    }

    /**
     * Removes an object from the cache.
     *
     * @param BaseModel $object
     * @return void
     */
    public function delete(BaseModel $object) 
    {
        $i = 0;
        $groupsToDelete = array();
        foreach($this->cachedObjectGroups as $cachedObjectGroup) {
            if($cachedObjectGroup->delete($object)) {
                $groupsToDelete[] = $i;
            }
            $i++;
        }

        if(count($groupsToDelete) > 0) {
            foreach($groupsToDelete as $groupId) {
                unset($this->cachedObjectGroups[$groupId]);
            }
            $this->cachedObjectGroups = array_values($this->cachedObjectGroups);
        }
    }

    /**
     * Uncache objects if a partial query matches.
     *
     * @param array $subQuery
     * @return void
     */
    public function uncache(array $subQuery)
    {
        $i = 0;
        $groupsToDelete = array();
        foreach($this->cachedObjectGroups as $cachedObjectGroup) {
            $queryString = $cachedObjectGroup->getQueryString();
            $queryArray = $this->queryStringToArray($queryString);            

            $match = true;
            foreach($subQuery as $key => $value) {
                if(!array_key_exists($key, $queryArray)) {
                    $match = false;
                    break;
                }                
                if($queryArray[$key] != $value) {
                    $match = false;
                    break;
                }
            }
            if ($match) {
                $groupsToDelete[] = $i;
            }

            $i++;
        }

        if(count($groupsToDelete) > 0) {
            foreach($groupsToDelete as $groupId) {
                unset($this->cachedObjectGroups[$groupId]);
            }
            $this->cachedObjectGroups = array_values($this->cachedObjectGroups);
        }
    }
}
