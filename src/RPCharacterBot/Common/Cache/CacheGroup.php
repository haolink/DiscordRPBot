<?php

namespace RPCharacterBot\Common\Cache;

use RPCharacterBot\Common\BaseModel;

class CacheGroup
{
    /**
     * String to describe the original query.
     *
     * @var string
     */
    private $queryString;

    /**
     * Objects which are stored within the cache.
     *
     * @var BaseModel[]
     */
    private $cachedObjects;

    /**
     * Creates a cache object block out of a query.
     *
     * @param string $queryString
     * @param BaseModel[] $cachedObjects
     */
    public function __construct(string $queryString, array $cachedObjects)
    {
        $this->queryString = $queryString;
        $this->cachedObjects = $cachedObjects;
    }

    /**
     * Get objects which are stored within the cache.
     *
     * @return BaseModel[]
     */ 
    public function getCachedObjects() : array
    {
        return $this->cachedObjects;
    }

    /**
     * Get string to describe the original query.
     *
     * @return string
     */ 
    public function getQueryString() : string
    {
        return $this->queryString;
    }

    /**
     * Deletes an object if it's in the cache.
     *
     * @param BaseModel $object
     * @return boolean  True if the cache group is empty afterwards.
     */
    public function delete(BaseModel $object) : bool 
    {
        $newObjects = array();
        foreach($this->cachedObjects as $cachedObject) {
            if($cachedObject !== $object) {
                $newObjects[] = $cachedObject;
            }
        }
        $this->cachedObjects = $newObjects;

        return (count($this->cachedObjects) == 0);
    }

    /**
     * Adds models to the cache
     *
     * @param BaseModel[] $objects
     * @return void
     */
    public function addObjects(array $objects)
    {
        foreach ($objects as $object) {
            if (!($object instanceof BaseModel)) {
                continue;
            }
            if (in_array($object, $this->cachedObjects)) {
                continue;
            }
            $this->cachedObjects[] = $object;
        }
    }
}
