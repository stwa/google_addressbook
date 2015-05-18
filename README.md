# Google Addressbook Plugin for Roundcube

This plugin lets you sync your Google Addressbook in readonly mode with Roundcube.

*Info: Initially I created this plugin for Roundcube 0.8.5, but it is still working properly with the current version of Roundcube (version 1.1.1). So yes, you can still use this plugin, it's up to date!*

## Requirements
* Roundcube >= v0.8.5 [http://roundcube.net/download]
* PHP 5.2.x or higher [http://www.php.net/]
* PHP Curl extension [http://www.php.net/manual/en/intro.curl.php]
* PHP JSON extension [http://php.net/manual/en/book.json.php]

## Installation
Use Composer for installation:  
http://plugins.roundcube.net/packages/stwa/google-addressbook  
  
*Do not forget to create the database table using the SQL from SQL/*

## Command Line
It is possible to sync the addressbooks via command line.  
To do this, you just have to run the script "sync-cli.sh".  
This syncs the addressbooks of all users who have enabled google addressbook plugin in their settings.  
  
You can also use crontab to sync the addressbooks periodically.  
Just specify an entry like:  
0 */4 * * * /path/to/roundcube/plugins/google_addressbook/sync-cli.sh  
(Every 4 hours in this example)

## Todo
* Login autosync too slow while waiting for contacts to load
* Add possibility to revoke tokens

## Contact
Author: Stefan Wagner (stw@cannycode.de)

Bug reports through github:  
https://github.com/stwa/google-addressbook/issues

## License
This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program. If not, see www.gnu.org/licenses/.

