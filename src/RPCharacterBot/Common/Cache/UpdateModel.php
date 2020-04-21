<?php

namespace RPCharacterBot\Common\Cache;

use RPCharacterBot\Common\BaseModel;

class UpdateModel
{
    /**
     * Priority of the update.
     *
     * @var int
     */
    private $priority;

    /**
     * Model to update.
     *
     * @var BaseModel
     */
    private $model;

    /**
     * Adds a model to the update queue.
     *
     * @param BaseModel $model
     * @param integer $priority
     */
    public function __construct(BaseModel $model, int $priority)
    {
        $this->priority = $priority;
        $this->model = $model;
    }
    

    /**
     * Get priority of the update.
     *
     * @return  int
     */ 
    public function getPriority()
    {
        return $this->priority;
    }

    /**
     * Get model to update.
     *
     * @return  BaseModel
     */ 
    public function getModel()
    {
        return $this->model;
    }
}
