<?php
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }
@unlink('/etc/cron.d/recurringwakeup');
@unlink('/var/lib/asterisk/agi-bin/recurringwakeup_agi.php');
@unlink('/etc/asterisk/extensions_recurringwakeup.conf');
$custom = '/etc/asterisk/extensions_custom.conf';
if (is_file($custom)) {
  $content = file_get_contents($custom);
  $content = preg_replace('/\n?; recurringwakeup module\n#include extensions_recurringwakeup\.conf\n?/', "\n", $content);
  file_put_contents($custom, $content);
}
// Databases and call logs are intentionally retained.
