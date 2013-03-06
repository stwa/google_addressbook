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

require_once(dirname(__FILE__) . '/google-api-php-client/src/Google_Client.php');
require_once(dirname(__FILE__) . '/google_addressbook_backend.php');
require_once(dirname(__FILE__) . '/xml_utils.php');

class google_addressbook extends rcube_plugin
{
  public $task = 'mail|addressbook|settings';
  private $abook_id = 'google_addressbook';
  private $abook_name = 'Google Addressbook';
  private $settings_key_token = 'google_current_token';
  private $settings_key_use_plugin = 'google_use_addressbook';
  private $settings_key_auto_sync = 'google_autosync';
  private $settings_key_auth_code = 'google_auth_code';
  private $client;

  function init()
  {
    $rcmail = rcmail::get_instance();

    $this->add_texts('localization/', true);
    $this->load_config('config.inc.php.dist');
    $this->load_config('config.inc.php');

    // register hooks
    $this->add_hook('preferences_list', array($this, 'preferences_list'));
    $this->add_hook('preferences_save', array($this, 'preferences_save'));
    $this->add_hook('addressbooks_list', array($this, 'addressbooks_list'));
    $this->add_hook('addressbook_get', array($this, 'addressbook_get'));
    $this->add_hook('contact_create', array($this, 'contact_create'));
    $this->add_hook('contact_update', array($this, 'contact_update'));
    $this->add_hook('contact_delete', array($this, 'contact_delete'));
    $this->register_action('plugin.google_addressbook', array($this, 'handle_ajax_requests'));

    $this->init_client();

    // add google addressbook to autocomplete addressbooks
    $sources = (array) $rcmail->config->get('autocomplete_addressbooks', 'sql');
    $sources[] = $this->abook_id;
    $rcmail->config->set('autocomplete_addressbooks', $sources);

    $this->include_script('google_addressbook.js');

    if($this->is_enabled() && $this->is_autosync()
      && !isset($_SESSION['google_addressbook_synced'])) {
      $this->google_sync_contacts();
    }
  }

  function init_client()
  {
    $this->client = new Google_Client();
    $this->client->setApplicationName('rc-google-addressbook');
    $this->client->setScopes("http://www.google.com/m8/feeds/");
    $this->client->setClientId('212349955974.apps.googleusercontent.com');
    $this->client->setClientSecret('7GZeYctgt3EPRz2x8i3pWfrb');
    $this->client->setRedirectUri('urn:ietf:wg:oauth:2.0:oob');
    $this->client->setAccessType('offline');
  }

  function get_current_token($from_db = false)
  {
    $prefs = rcmail::get_instance()->user->get_prefs();
    return $prefs[$this->settings_key_token];
  }
  
  function save_current_token($token)
  {
    $prefs = array($this->settings_key_token => $token);
    if(!rcmail::get_instance()->user->save_prefs($prefs)) {
      // TODO: error handling
    }
  }

  function is_enabled()
  {
    $val = rcmail::get_instance()->config->get($this->settings_key_use_plugin);
    return (bool)$val;
  }

  function is_autosync()
  {
    $val = rcmail::get_instance()->config->get($this->settings_key_auto_sync);
    return (bool)$val;
  }

  function handle_ajax_requests()
  {
    $action = get_input_value('_act', RCUBE_INPUT_GPC);
    if($action == 'sync') {
      $this->google_sync_contacts();
    }
    rcmail::get_instance()->output->command('plugin.google_addressbook_finished', array('message' => $this->gettext('done')));
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
        'content' => $checkbox->show($rcmail->config->get($this->settings_key_use_plugin))
      );

      $field_id = 'rc_google_autosync';
      $checkbox = new html_checkbox(array('name' => $field_id, 'id' => $field_id, 'value' => 1));
      $params['blocks'][$this->id]['options'][$field_id] = array(
        'title' => html::label($field_id, $this->gettext('autosync')),
        'content' => $checkbox->show($rcmail->config->get($this->settings_key_auto_sync))
      );

      $field_id = 'rc_google_authcode';
      $input_auth = new html_inputfield(array('name' => $field_id, 'id' => $field_id, 'size' => 45));
      $params['blocks'][$this->id]['options'][$field_id] = array(
        'title' => html::label($field_id, $this->gettext('authcode')),
        'content' => $input_auth->show($rcmail->config->get($this->settings_key_auth_code))
      );

      $params['blocks'][$this->id]['options']['link'] = array(
        'title' => html::span('', ''),
        'content' => html::a(array('href' => $this->client->createAuthUrl(), 'target' => '_blank'), $this->gettext('authcodelink'))
      );
    }
    return $params;
  }

  function preferences_save($params)
  {
    if($params['section'] == 'addressbook') {
      $old_prefs = rcmail::get_instance()->user->get_prefs();
      $new_code = get_input_value('rc_google_authcode', RCUBE_INPUT_POST);
      if($old_prefs[$this->settings_key_auth_code] != $new_code) {
        // token is no longer valid, so delete it
        $this->save_current_token(null);
      }
      $params['prefs'][$this->settings_key_use_plugin] = isset($_POST['rc_use_plugin']) ? true : false;
      $params['prefs'][$this->settings_key_auto_sync] = isset($_POST['rc_google_autosync']) ? true : false;
      $params['prefs'][$this->settings_key_auth_code] = $new_code;
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
      $params['instance'] = new google_addressbook_backend($this->abook_name, $rcmail->get_dbh(), $rcmail->user->ID);
      $params['writable'] = false;
    }

    return $params;
  }

  function google_authenticate($code)
  {
    $rcmail = rcmail::get_instance();

    $token = $this->get_current_token();
    if($token != null) {
      $this->client->setAccessToken($token);
    }

    $success = false;
    write_log('google_addressbook', print_r(json_decode($this->client->getAccessToken()),true));
    try {
      if($this->client->getAccessToken() == null) {
        $this->client->authenticate($code);
        $success = true;
      } else if($this->client->isAccessTokenExpired()) {
        $token = json_decode($this->client->getAccessToken());
        if(empty($token->refresh_token)) {
          // this only happens if google client id is wrong ang access type != offline
        } else {
          $this->client->refreshToken($token->refresh_token);
          $success = true;
        }
      } else {
        // token valid, nothing to do.
        $success = true;
      }
    } catch(Exception $e) {
      $rcmail->output->show_message($e->getMessage(), 'error');
    }

    if($success) {
      $token = $this->client->getAccessToken();
      $this->save_current_token($token);
    }

    return $success;
  }

  function google_sync_contacts()
  {
    write_log('google_addressbook', 'google_sync_contacts');
    $rcmail = rcmail::get_instance();
    $code = $rcmail->config->get($this->settings_key_auth_code);
 
    $_SESSION['google_addressbook_synced'] = true;
   
    if(!$this->google_authenticate($code)) {
      return;
    }
    
    $feed = 'https://www.google.com/m8/feeds/contacts/default/full'.'?max-results=9999'.'&v=3.0';
    $val = $this->client->getIo()->authenticatedRequest(new Google_HttpRequest($feed));
    if($val->getResponseHttpCode() != 200) {
      // TODO: error
      return;
    }
    
    $xml = xml_utils::xmlstr_to_array($val->getResponseBody());
    $num_entries = count($xml['entry']);
    
    write_log('response', 'getting contact: '.print_r($val->getResponseBody(), true));
    $rcmail->output->show_message($num_entries.$this->gettext('contactsfound'), 'confirmation');

    $backend = new google_addressbook_backend($this->abook_name, $rcmail->get_dbh(), $rcmail->user->ID);
    $backend->delete_all();
    
    foreach($xml['entry'] as $entry) {
      write_log('google_addressbook', 'getting contact: '.$entry['title'][0]['@text']);
      //write_log('google_addressbook', 'getting contact: '.print_r($entry,true));
      $record = array();
      $name = $entry['gd:name'][0];
      $record['name']= $name['gd:fullName'][0]['@text'];
      $record['firstname'] = $name['gd:givenName'][0]['@text'];
      $record['surname'] = $name['gd:familyName'][0]['@text'];
      $record['middlename'] = $name['gd:additionalName'][0]['@text'];
      $record['prefix'] = $name['gd:namePrefix'][0]['@text'];
      $record['suffix'] = $name['gd:nameSuffix'][0]['@text'];
      if(empty($record['name'])) {
        $record['name'] = $entry['title'][0]['@text'];
      }

      foreach($entry['gd:email'] as $email) {
        list($rel, $type) = explode('#', $email['@attributes']['rel'], 2);
        $type = empty($type) ? '' : ':'.$type;
        $record['email'.$type] = $email['@attributes']['address'];
      }

      foreach($entry['gd:phoneNumber'] as $phone) {
        list($rel, $type) = explode('#', $phone['@attributes']['rel'], 2);
        $type = empty($type) ? '' : ':'.$type;
        $record['phone'.$type] = $phone['@text'];
      }
      
      foreach($entry['link'] as $link) {
        $rel = $link['@attributes']['rel'];
        $href = $link['@attributes']['href'];
        if($rel == 'http://schemas.google.com/contacts/2008/rel#photo') {
          // etag is only set if image is available
          if(isset($link['@attributes']['etag'])) {
            $resp = $this->client->getIo()->authenticatedRequest(new Google_HttpRequest($href));
            if($resp->getResponseHttpCode() == 200) {
              $record['photo'] = $resp->getResponseBody();
            }
          }
          break;
        }
      }

      $backend->insert($record, false);
    }
  }

  function contact_create($params)
  {
    write_log('google_addressbook', 'contact_create: '.print_r($params, true));
    // TODO: not supported right now
  }

  function contact_update($params)
  {
    write_log('google_addressbook', 'contact_update: '.print_r($params, true));
    // TODO: not supported right now
  }

  function contact_delete($params)
  {
    write_log('google_addressbook', 'contact_delete: '.print_r($params, true));
    // TODO: not supported right now
  }
}

?>
