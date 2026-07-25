<?php
/** FreePBX module installation */
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }

$dbh = FreePBX::Database();
$dbh->exec("CREATE TABLE IF NOT EXISTS recurringwakeup_jobs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  target VARCHAR(20) NOT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  time_mode VARCHAR(12) NOT NULL DEFAULT 'common',
  common_time TIME NULL,
  mon_time TIME NULL,
  tue_time TIME NULL,
  wed_time TIME NULL,
  thu_time TIME NULL,
  fri_time TIME NULL,
  sat_time TIME NULL,
  sun_time TIME NULL,
  ring_seconds SMALLINT UNSIGNED NOT NULL DEFAULT 60,
  max_attempts TINYINT UNSIGNED NOT NULL DEFAULT 3,
  retry_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 10,
  snooze_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 5,
  callerid VARCHAR(80) NOT NULL DEFAULT 'Weckruf <*68>',
  last_run_key VARCHAR(40) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$dbh->exec("CREATE TABLE IF NOT EXISTS recurringwakeup_queue (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  job_id INT UNSIGNED NOT NULL,
  due_at DATETIME NOT NULL,
  attempt TINYINT UNSIGNED NOT NULL DEFAULT 1,
  reason VARCHAR(20) NOT NULL DEFAULT 'scheduled',
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL,
  processed_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY due_status (status, due_at),
  KEY job_id (job_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$dbh->exec("CREATE TABLE IF NOT EXISTS recurringwakeup_log (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  job_id INT UNSIGNED NULL,
  queue_id BIGINT UNSIGNED NULL,
  event_time DATETIME NOT NULL,
  status VARCHAR(30) NOT NULL,
  detail VARCHAR(255) NULL,
  PRIMARY KEY (id),
  KEY job_time (job_id, event_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");


$dbh->exec("CREATE TABLE IF NOT EXISTS recurringwakeup_settings (
  setting_key VARCHAR(64) NOT NULL,
  setting_value TEXT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$now = date('Y-m-d H:i:s');
$defaults = array(
  'telegram_enabled' => '0',
  'telegram_bot_token' => '',
  'telegram_chat_id' => ''
);
$setDefault = $dbh->prepare("INSERT IGNORE INTO recurringwakeup_settings (setting_key,setting_value,updated_at) VALUES (?,?,?)");
foreach ($defaults as $key => $value) { $setDefault->execute(array($key,$value,$now)); }

$moduleDir = __DIR__;
$copies = array(
  array($moduleDir . '/agi-bin/recurringwakeup_agi.php', '/var/lib/asterisk/agi-bin/recurringwakeup_agi.php', 0755),
  array($moduleDir . '/etc/asterisk/extensions_recurringwakeup.conf', '/etc/asterisk/extensions_recurringwakeup.conf', 0644),
);
foreach ($copies as $copy) {
  if (!@copy($copy[0], $copy[1])) {
    out(sprintf(_('Warning: Could not copy %s to %s'), $copy[0], $copy[1]));
  } else {
    @chmod($copy[1], $copy[2]);
    @chown($copy[1], 'asterisk');
    @chgrp($copy[1], 'asterisk');
  }
}

$soundTargets = array(
  '/var/lib/asterisk/sounds/recurringwakeup',
  '/var/lib/asterisk/sounds/de/recurringwakeup'
);
foreach ($soundTargets as $dir) {
  if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
  foreach (array('wakeup.wav','options.wav','confirmed.wav','snooze.wav','noinput.wav') as $sound) {
    @copy($moduleDir . '/sounds/' . $sound, $dir . '/' . $sound);
    @chmod($dir . '/' . $sound, 0644);
    @chown($dir . '/' . $sound, 'asterisk');
    @chgrp($dir . '/' . $sound, 'asterisk');
  }
}

$custom = '/etc/asterisk/extensions_custom.conf';
$include = '#include extensions_recurringwakeup.conf';
$content = is_file($custom) ? file_get_contents($custom) : '';
if (strpos($content, $include) === false) {
  file_put_contents($custom, rtrim($content) . "\n\n; recurringwakeup module\n" . $include . "\n");
}

$cron = "# FreePBX recurringwakeup scheduler\n* * * * * asterisk /usr/bin/php " . $moduleDir . "/bin/scheduler.php >/dev/null 2>&1\n";
if (@file_put_contents('/etc/cron.d/recurringwakeup', $cron) !== false) {
  @chmod('/etc/cron.d/recurringwakeup', 0644);
} else {
  out(_('Warning: Could not create /etc/cron.d/recurringwakeup. Create the cron entry manually.'));
}

out(_('Recurring Wakeup installed. Run fwconsole reload after installation.'));
