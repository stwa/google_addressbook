/**
 * Javascript events and handlers
 *
 * @version 1.0
 * @author Stefan L. Wagner
 */
if (window.rcmail) {
  rcmail.addEventListener('init', function(evt) {
    var button = $('<A>').attr('id', 'rcmbtnsyncgoogle').attr('href', '#');
    button.addClass('button checkmail').html(rcmail.gettext('Sync Google', 'google_addressbook'));
    button.bind('click', function(e){ return rcmail.command('google_addressbook.sync', this); });
  
    rcmail.add_element(button, 'toolbar');
    rcmail.register_button('google_addressbook.sync', 'rcmbtnsyncgoogle', 'link');
    rcmail.register_command('google_addressbook.sync', sync_handler, true);
    rcmail.addEventListener('plugin.finished', sync_finished);
  });

  function sync_handler() {
    var lock = rcmail.set_busy(true, 'sync');
    rcmail.http_post('plugin.google_addressbook', '_act=sync', lock);
  }

  function sync_finished(response) {
    rcmail.command('list','google_addressbook');
  }
}
