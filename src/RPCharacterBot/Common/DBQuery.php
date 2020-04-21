<?php

namespace RPCharacterBot\Common;

class DBQuery
{
    /**
     * SQL query.
     *
     * @var string
     */
    private $sql;

    /**
     * SQL parameters.
     *
     * @var array
     */
    private $parameters;

    public function __construct(string $sql, array $parameters = array())
    {
        $this->sql = $sql;
        $this->parameters = $parameters;
    }

    /**
     * Get sQL parameters.
     *
     * @return  array
     */ 
    public function getParameters() : array
    {
        return $this->parameters;
    }

    /**
     * Get sQL query.
     *
     * @return  string
     */ 
    public function getSql() : string
    {
        return $this->sql;
    }
}
