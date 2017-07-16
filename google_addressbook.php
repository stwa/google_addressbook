<?php

/**
 * Google Addressbook
 *
 * Plugin to use google contacts in roundcube mail.
 *
 * @version 1.0
 * @author Stefan L. Wagner
 * @url https://github.com/stwa/google-addressbook
 */

require_once(dirname(__FILE__) . '/google_addressbook_backend.php');
require_once(dirname(__FILE__) . '/google_func.php');

class google_addressbook extends rcube_plugin
{
  public $task = 'mail|addressbook|settings';
  private $abook_id = 'google_addressbook';
  private $abook_name = 'Google Addressbook';

  function init()
  {
    $rcmail = rcmail::get_instance();

    $this->add_texts('localization/', true);
    $this->load_config('config.inc.php.dist');
    $this->load_config('config.inc.php');

    // register actions
    $this->register_action('plugin.google_addressbook.auth', array($this, 'handle_auth_requests'));
    $this->register_action('plugin.google_addressbook.sync', array($this, 'handle_sync_requests'));

    // register hooks
    $this->add_hook('preferences_list', array($this, 'preferences_list'));
    $this->add_hook('preferences_save', array($this, 'preferences_save'));
    $this->add_hook('addressbooks_list', array($this, 'addressbooks_list'));
    $this->add_hook('addressbook_get', array($this, 'addressbook_get'));
    $this->add_hook('contact_create', array($this, 'contact_create'));
    $this->add_hook('contact_update', array($this, 'contact_update'));
    $this->add_hook('contact_delete', array($this, 'contact_delete'));

    // add google addressbook to autocomplete addressbooks
    $sources = (array) $rcmail->config->get('autocomplete_addressbooks', 'sql');
    $sources[] = $this->abook_id;
    $rcmail->config->set('autocomplete_addressbooks', $sources);

    $this->include_script('google_addressbook.js');

    // only call command when in ajax action 'list'
    if ($rcmail->output->type == 'js' && $rcmail->action == 'list') {
      if($this->is_enabled() && $this->is_autosync() && !isset($_SESSION['google_addressbook_synced'])) {
        $rcmail->output->command('plugin.google_addressbook.autosync', array('message' => $this->gettext('done')));
      }
    }
  }

  function get_current_token($from_db = false)
  {
    return google_func::get_current_token(rcmail::get_instance()->user, $from_db);
  }

  function save_current_token($token)
  {
    return google_func::save_current_token(rcmail::get_instance()->user, $token);
  }

  function is_enabled()
  {
    return google_func::is_enabled(rcmail::get_instance()->user);
  }

  function is_autosync()
  {
    return google_func::is_autosync(rcmail::get_instance()->user);
  }

  function handle_auth_requests(){
      $rcmail = rcmail::get_instance();
      if (isset($_GET['error'])){
          $rcmail->output->show_message(htmlspecialchars($_GET['error']), 'error');
          return;
      }
      $auth_code = $_GET['code'];
      $user = $rcmail->user;
      $prefs = array(google_func::$settings_key_auth_code => $auth_code, google_func::$settings_key_token => null);
      if(!$user->save_prefs($prefs)) {
          $rcmail->display_server_error('errorsaving');
          return;
      }
      $client = google_func::get_client();
      $res = google_func::google_authenticate($client, $user);
      $rcmail->output->show_message($res['message'], $res['success'] ? 'confirmation' : 'error');
  }

  function handle_sync_requests()
  {
    $this->sync_contacts();
    rcmail::get_instance()->output->command('plugin.google_addressbook.finished', array('message' => $this->gettext('done')));
  }

  function preferences_list($params)
  {
    $rcmail = rcmail::get_instance();
    if($params['section'] == 'addressbook') {
      $params['blocks'][$this->id]['name'] = $this->abook_name;

      $field_id = 'rc_use_plugin';
      $checkbox = new html_checkbox(array('name' => $field_id, 'id' => $field_id, 'value' => 1));
      $params['blocks'][$this->id]['options'][$field_id] = array(
        'title' => html::label($field_id, $this->gettext('use').$this->abook_name),
        'content' => $checkbox->show($rcmail->config->get(google_func::$settings_key_use_plugin))
      );

      $field_id = 'rc_google_autosync';
      $checkbox = new html_checkbox(array('name' => $field_id, 'id' => $field_id, 'value' => 1));
      $params['blocks'][$this->id]['options'][$field_id] = array(
        'title' => html::label($field_id, $this->gettext('autosync')),
        'content' => $checkbox->show($rcmail->config->get(google_func::$settings_key_auto_sync))
      );
      $auth_link = array('target' => '_top');
      if (!google_func::has_redirect()){
          $field_id = 'rc_google_authcode';
          $input_auth = new html_inputfield(array('name' => $field_id, 'id' => $field_id, 'size' => 45));
          $params['blocks'][$this->id]['options'][$field_id] = array(
            'title' => html::label($field_id, $this->gettext('authcode')),
            'content' => $input_auth->show($rcmail->config->get(google_func::$settings_key_auth_code))
          );
          $auth_link['target'] = '_blank';
      }
      $auth_link['href'] = google_func::get_client()->createAuthUrl();
      $params['blocks'][$this->id]['options']['link'] = array(
        'title' => html::span('', ''),
        'content' => html::a($auth_link, $this->gettext('authcodelink'))
      );
    }
    return $params;
  }

  function preferences_save($params)
  {
    if($params['section'] == 'addressbook') {
      if (!google_func::has_redirect()){
          $old_prefs = rcmail::get_instance()->user->get_prefs();
          $new_code = rcube_utils::get_input_value('rc_google_authcode', rcube_utils::INPUT_POST);
          if($old_prefs[google_func::$settings_key_auth_code] != $new_code) {
            // token is no longer valid, so delete it
            $this->save_current_token(null);
          }
          $params['prefs'][google_func::$settings_key_auth_code] = $new_code;
      }
      $params['prefs'][google_func::$settings_key_use_plugin] = isset($_POST['rc_use_plugin']) ? true : false;
      $params['prefs'][google_func::$settings_key_auto_sync] = isset($_POST['rc_google_autosync']) ? true : false;
    }
    return $params;
  }

  // roundcube collects information about available addressbooks
  function addressbooks_list($params)
  {
    if($this->is_enabled()) {
      $params['sources'][] = array('id' => $this->abook_id,
                                   'name' => $this->abook_name,
                                   'groups' => false,
                                   'readonly' => true,
                                   'autocomplete' => true);
    }
    return $params;
  }

  // user opens addressbook
  function addressbook_get($params)
  {
    $rcmail = rcmail::get_instance();
    if($params['id'] == $this->abook_id) {
      $params['instance'] = new google_addressbook_backend($rcmail->get_dbh(), $rcmail->user->ID);
      $params['writable'] = false;
    }

    return $params;
  }

  function sync_contacts()
  {
    $rcmail = rcmail::get_instance();

    $_SESSION['google_addressbook_synced'] = true;

    $res = google_func::google_sync_contacts($rcmail->user);
    $rcmail->output->show_message($res['message'], $res['success'] ? 'confirmation' : 'error');
  }

  function contact_create($params)
  {
    rcube::write_log('google_addressbook', 'contact_create: '.print_r($params, true));
    // TODO: not supported right now
  }

  function contact_update($params)
  {
    rcube::write_log('google_addressbook', 'contact_update: '.print_r($params, true));
    // TODO: not supported right now
  }

  function contact_delete($params)
  {
    rcube::write_log('google_addressbook', 'contact_delete: '.print_r($params, true));
    // TODO: not supported right now
  }
}
