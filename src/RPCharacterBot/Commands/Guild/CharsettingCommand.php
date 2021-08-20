<?php

namespace RPCharacterBot\Commands\Guild;

use RPCharacterBot\Commands\GuildCommand;
use React\Promise\ExtendedPromiseInterface;
use RPCharacterBot\Model\Guild;

class CharsettingCommand extends GuildCommand
{    
    /**
     * User executing this command requires the following permissions.
     *
     * @var array
     */
    protected static $REQUIRED_USER_PERMISSIONS = array('administrator');
    
    /**
     * Sets up whether characters in this guild are per channel or guild wide.
     *
     * @return ExtendedPromiseInterface|null
     */
    protected function handleCommandInternal(): ?ExtendedPromiseInterface
    {
        $words = $this->getMessageWords();

        if(count($words) < 1) {
            return $this->sendSelfDeletingReply('Usage: ' . $this->messageInfo->mainPrefix . 'charsetting [server/channel/thread]');
        }

        $firstWord = mb_strtolower($words[0]);

        if (!in_array($firstWord, array('server', 'channel', 'guild', 'thread'))) {
            return $this->sendSelfDeletingReply('Usage: ' . $this->messageInfo->mainPrefix . 'charsetting [server/channel/thread]');
        }
        
        $newSetting = Guild::RPCHAR_SETTING_CHANNEL;
        switch ($firstWord) {
            case 'server':
            case 'guild':
                $newSetting = Guild::RPCHAR_SETTING_GUILD;
                break;
            case 'channel':
                $newSetting = Guild::RPCHAR_SETTING_CHANNEL;
                break;
            case 'thread':
            default:
                $newSetting = Guild::RPCHAR_SETTING_THREAD;
                break;
        }

        $newSettingName = null;
        switch ($newSetting) {
            case Guild::RPCHAR_SETTING_GUILD:
                $newSettingName = 'server';
                break;
            case Guild::RPCHAR_SETTING_THREAD:
                $newSettingName = 'thread (with channel fallback)';
                break;
            case Guild::RPCHAR_SETTING_CHANNEL:
            default:
                $newSettingName = 'channel';
                break;
        }

        $currentSetting = $this->messageInfo->guild->getRpCharacterSetting();

        if ($currentSetting == $newSetting) {
            return $this->sendSelfDeletingReply('Character selection in this server is already per ' . $newSettingName . '.');
        } else {
            $this->messageInfo->guild->setRpCharacterSetting($newSetting);
            return $this->sendSelfDeletingReply('Character selection in this server is now changed to same ' . 
                'character per ' . $newSettingName . '.');
        }        
    }
}
