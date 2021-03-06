<?php

namespace RPCharacterBot\Common;

/**
 * Enhanced base model class providing a method to determine the default
 * character ID depending on guild settings.
 */
abstract class CharacterDefaultModel extends BaseModel
{
    /** 
     * Gets the default character ID. (Stored as BigInt in db).
     * @return string|null
     */
    abstract function getDefaultCharacterId() : ?string;

    /**
     * Sets the default character ID for a player within a guild/channel.
     * (Stored as BigInt in db).
     *
     * @param string|null $characterId
     * @return void
     */
    abstract function setDefaultCharacterId(?string $characterId) : void;

    /** 
     * Gets the former character ID. (Stored as BigInt in db).
     * @return string|null
     */ 
    abstract public function getFormerCharacterId() : ?string;

    /**
     * Sets the former character ID for a player within a guild/channel.
     * (Stored as BigInt in db).
     *
     * @param string|null  $formerCharacterId  (BigInt in DB).
     * @return void
     */ 
    abstract public function setFormerCharacterId(?string $formerCharacterId);    
}
