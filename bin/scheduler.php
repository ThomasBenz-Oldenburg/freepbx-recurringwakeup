#!/usr/bin/php -q
<?php
require_once '/etc/freepbx.conf';
$lock = fopen('/var/run/asterisk/recurringwakeup.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) { exit(0); }
$dbh = FreePBX::Database();
$now = new DateTime('now');
$nowSql = $now->format('Y-m-d H:i:s');
$weekday = (int)$now->format('N');
$cols = array(1=>'mon_time',2=>'tue_time',3=>'wed_time',4=>'thu_time',5=>'fri_time',6=>'sat_time',7=>'sun_time');
$col = $cols[$weekday];
$minute = $now->format('H:i');
$runKey = $now->format('Y-m-d') . '-' . $minute;

$jobs = $dbh->query("SELECT * FROM recurringwakeup_jobs WHERE enabled=1")->fetchAll(PDO::FETCH_ASSOC);
foreach ($jobs as $job) {
  $scheduled = null;
  if ($job['time_mode'] === 'common') {
    if (!empty($job[$col])) { $scheduled = substr($job['common_time'], 0, 5); }
  } else {
    if (!empty($job[$col])) { $scheduled = substr($job[$col], 0, 5); }
  }
  if ($scheduled === $minute && $job['last_run_key'] !== $runKey) {
    $ins = $dbh->prepare("INSERT INTO recurringwakeup_queue (job_id,due_at,attempt,reason,status,created_at) VALUES (?,?,1,'scheduled','pending',?)");
    $ins->execute(array($job['id'], $nowSql, $nowSql));
    $up = $dbh->prepare('UPDATE recurringwakeup_jobs SET last_run_key=? WHERE id=?');
    $up->execute(array($runKey, $job['id']));
  }
}

$q = $dbh->prepare("SELECT q.*, j.target, j.ring_seconds, j.max_attempts, j.retry_minutes, j.callerid, j.enabled
 FROM recurringwakeup_queue q JOIN recurringwakeup_jobs j ON j.id=q.job_id
 WHERE q.status='pending' AND q.due_at<=? ORDER BY q.id LIMIT 20");
$q->execute(array($nowSql));
$rows = $q->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $row) {
  if (!$row['enabled']) {
    $u = $dbh->prepare("UPDATE recurringwakeup_queue SET status='paused', processed_at=? WHERE id=?");
    $u->execute(array($nowSql, $row['id']));
    continue;
  }
  $target = preg_replace('/[^0-9]/', '', $row['target']);
  if ($target === '' || strlen($target) > 20) {
    $u = $dbh->prepare("UPDATE recurringwakeup_queue SET status='invalid', processed_at=? WHERE id=?");
    $u->execute(array($nowSql, $row['id']));
    continue;
  }
  $tmp = '/var/spool/asterisk/tmp/recurringwakeup-' . $row['id'] . '-' . getmypid() . '.call';
  $out = '/var/spool/asterisk/outgoing/recurringwakeup-' . $row['id'] . '.call';
  $callerid = str_replace(array("\r","\n"), '', $row['callerid']);
  $maxRetries = max(0, (int)$row['max_attempts'] - 1);
  $retryTime = max(60, (int)$row['retry_minutes'] * 60);
  $waitTime = max(15, min(300, (int)$row['ring_seconds']));
  $content = "Channel: Local/{$target}@from-internal/n\n";
  $content .= "CallerID: {$callerid}\n";
  $content .= "MaxRetries: {$maxRetries}\nRetryTime: {$retryTime}\nWaitTime: {$waitTime}\n";
  $content .= "Context: recurringwakeup-answer\nExtension: s\nPriority: 1\n";
  $content .= "Setvar: RW_JOB_ID={$row['job_id']}\nSetvar: RW_QUEUE_ID={$row['id']}\nSetvar: RW_ATTEMPT={$row['attempt']}\n";
  if (file_put_contents($tmp, $content) !== false) {
    chmod($tmp, 0660);
    @chown($tmp, 'asterisk'); @chgrp($tmp, 'asterisk');
    if (@rename($tmp, $out)) {
      $u = $dbh->prepare("UPDATE recurringwakeup_queue SET status='dialing', processed_at=? WHERE id=?");
      $u->execute(array($nowSql, $row['id']));
      $l = $dbh->prepare("INSERT INTO recurringwakeup_log (job_id,queue_id,event_time,status,detail) VALUES (?,?,?,?,?)");
      $l->execute(array($row['job_id'],$row['id'],$nowSql,'dialing','Ziel '.$target.', Versuch '.$row['attempt']));
    }
  }
}
flock($lock, LOCK_UN);
fclose($lock);
