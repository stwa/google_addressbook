#!/usr/bin/env php
<?php
/*
 +-----------------------------------------------------------------------+
 | sync-cli.sh                                                           |
 |                                                                       |
 | Licensed under the GNU General Public License version 3               |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Sync your google addressbook via cli.                               |
 +-----------------------------------------------------------------------+
 | Author: Stefan Wagner <stw@cannycode.de>                              |
 +-----------------------------------------------------------------------+
*/

define('INSTALL_PATH', realpath(dirname(__FILE__) . '/../..') . '/' );

require_once INSTALL_PATH.'program/include/clisetup.php';
require_once(dirname(__FILE__) . '/google_func.php');

ini_set('memory_limit', -1);

// connect to DB
$rcmail = rcmail::get_instance();

$db = $rcmail->get_dbh();
$db->db_connect('w');

if (!$db->is_connected() || $db->is_error())
    die("No DB connection\n");

$sql_result = $db->query("SELECT * FROM ".$db->table_name('users'));
while ($sql_result && ($sql_arr = $db->fetch_assoc($sql_result))) {
    echo "Syncing contacts for user " . $sql_arr['user_id'] . "...";

    $user = new rcube_user($sql_arr['user_id'], $sql_arr);
    if(google_func::is_enabled($user)) {
      $res = google_func::google_sync_contacts($user);
      echo $res['message']."\n";
    } else {
      echo "disabled.\n";
    }
}

?>
