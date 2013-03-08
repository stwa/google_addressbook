<?php

/**
 * Model class for the google addressbook database
 *
 * @version 1.0
 * @author Stefan L. Wagner
 */

class google_addressbook_backend extends rcube_contacts
{
  private $user_id;
  private $db;

  function __construct($dbconn, $user)
  {
    parent::__construct($dbconn, $user);
    $this->user_id = $user;
    $this->db = $dbconn;
    $this->name = 'Google Addressbook';
    $this->readonly = true;
    $this->groups = false;
    $this->undelete = false;
    $this->db_name = get_table_name('contacts_google');
  }

  function delete_all()
  {
    $query = "DELETE FROM $this->db_name WHERE user_id=?";
    $this->db->query($query, $this->user_id);
    return $this->db->affected_rows();
  }
}

?>
