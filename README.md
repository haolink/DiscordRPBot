# Commands

By default commands are prefixed with `rp!`. Within an RP channel commands use the shorter prefix `..`. Both prefixes can be changed by a server admin in case they conflict with an existing command.


##Server admin commands

The following commands can only be run by server administrators or server owners.

### Changing prefixes to avoid conflicts with other bots

* `@RPBot prefix [newprefix]`  
To change the main prefix (by default `rp!`) you must ping the bot in a message. This is to avoid interferring with other bots. All upcoming command descriptions use `rp!` for the description
* `rp!sprefix [new short prefix]`  
To change the prefix within RP channels (by default `..`) you can use this command. In your own interest keep this prefix as short as possible. Users with many characters will need it a lot.  
  
### Server specific setup 

* `rp!charsetting [server/channel]`  
  If a user switches character in a Switches the server setting - is a player character channel specific or server specific (by default: channel specific).  
  
### RP channel setup

* `rp!setup`  
   Instruct the bot to take over a channel. This channel becomes an RP channel, user messages will be replaced by RP characters. The bot requires the *Manage Messages* and *Manage Webhooks* permission in this channel. It will complain if it doesn't have either. You can remove the *Manage Webhooks* permission if you wish afterwards - it only requires it during setup and unsetting.
* `rp!ooc [on/off]`  
Allow OOC talk in this channel (by default: on).
* `rp!unset`  
Instruct the bot to seize control over a channel.



## User commands

The following commands can be run by all users.


### Accessing character setup help (all users)
* `rp!help`  
  Sends a DM to the user on how to set up their characters.
* `..help` (only within RP channels)  
  Sends a DM to the user on how to set up their characters.


### Character setup (via DM, all users)
Setting up characters happens in DMs after a user issues the help command. This way the user won't disrupt any server conversations. Characters set up by a user are accessible accross all servers which have the bot. These commands are not prefixed.

* `list`  
Shows a list of all characters you added.  
* `new [character shortcut] [nickname]`  
Adds a new character - the character has a Discord nickname which must follow the Discord Username guidelines. Character shortcuts may only be letters, numbers, dashes and underscore - no spaces. The shortcut cannot be changed. You can use this character in all servers which run this bot.
* `nick [character shortcut] [nickname]`  
Changes the discord nickname of a character. This change will only apply to new messages of the character.
* `avatar [character shortcut] [url]`  
Sets up the avatar of the character. This will only apply to new messages of the character.
* `delete [character shortcut]`  
Removes the character. You will not be asked for confirmation. You will have to recreate the character in case you accidentially delete it.   
* `default [character shortcut]`  
Change your default character. This is the character you will use in case no character has been select for the RP channel you join.  
* `ooc [string]`  
Sets up an out of character sequence for the user (by default: `//`). If a message starts with this sequence, messages in RP channels will be ignored by the RP bot.



### Within an RP channel

* `..sw [character shortcut]`  
Switches the character you're of the user for this channel or server (depending on the server setting - by default it'll be only changed for the current channel).
* `..del`  
Deletes your last submitted message (can only delete one message). Unlike usually you cannot use the Discord "Delete Message" functionality this time.
* `..swlast [character shortcut]`  
In case you submitted your last message with the wrong character this command will change the character of the message to another one. You will also switch to this character.