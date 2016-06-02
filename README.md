# Google Addressbook Plugin for Roundcube

This plugin lets you sync your Google Addressbook in readonly mode with Roundcube.

*Info: Initially I created this plugin for Roundcube 0.8.5, but it is still working properly with the current version of Roundcube (version 1.2.0). So yes, you can still use this plugin, it's up to date!*

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

## Own Google Application
You can register your plugin with Google to customize the application name that is presented to users when requesting access to contacts. For this, you have to register at https://console.developers.google.com/ and create a project for your roundcube installation. You get an application name, a client id and a secret. Ensure to allow redirect to `https://your-rc-base..../?_task=settings&_action=plugin.google_addressbook_auth` when you create the Web Application credential and also store this to `google_addressbook_client_redirect_url`. Alternatively you can create an Other credential, but then disable `google_addressbook_client_redirect`. Anyhow remember to enable Contacts API for that project. Put all these values in a file named `config.inc.php` inside the `plugins/google_addressbook` folder like this:
```
<?php
$config['google_addressbook_application_name'] = 'your-application-name';
$config['google_addressbook_client_id'] = 'your-application-id';
$config['google_addressbook_client_secret'] = 'your-application-secret';
$config['google_addressbook_client_redirect'] = true;
$config['google_addressbook_client_redirect_url'] = 'https://your-rc-base..../?_task=settings&_action=plugin.google_addressbook_auth';
```
Be aware that all existing oauth tokens will not work any more and the users have to request a new access token from Google. So you might want to do this change in a new installation only.

## Contact
Author: Stefan Wagner (github@stwa.name)

Bug reports through github:  
https://github.com/stwa/google-addressbook/issues

## License
This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program. If not, see www.gnu.org/licenses/.
