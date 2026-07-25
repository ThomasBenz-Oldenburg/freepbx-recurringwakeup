#!/usr/bin/php -q
<?php
require_once '/etc/freepbx.conf';
require_once '/var/www/html/admin/modules/recurringwakeup/lib.php';

$jobId = isset($argv[1]) ? (int)$argv[1] : 0;
$queueId = isset($argv[2]) ? (int)$argv[2] : 0;
$attempt = isset($argv[3]) ? max(1, (int)$argv[3]) : 1;
$result = isset($argv[4]) ? preg_replace('/[^a-z]/', '', strtolower($argv[4])) : 'unknown';
$allowed = array('confirmed','snooze','noinput','hangup');
if (!in_array($result, $allowed, true)) { $result = 'unknown'; }

$dbh = FreePBX::Database();
$stmt = $dbh->prepare('SELECT * FROM recurringwakeup_jobs WHERE id = ?');
$stmt->execute(array($jobId));
$job = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$job) { exit(0); }

// Prevent a late hangup handler from processing a queue item a second time.
if ($queueId) {
  $qs = $dbh->prepare('SELECT status FROM recurringwakeup_queue WHERE id=?');
  $qs->execute(array($queueId));
  $queueStatus = $qs->fetchColumn();
  if ($queueStatus === 'completed' && $result === 'hangup') { exit(0); }
}

$now = date('Y-m-d H:i:s');
$detail = 'Versuch ' . $attempt;
$log = $dbh->prepare('INSERT INTO recurringwakeup_log (job_id, queue_id, event_time, status, detail) VALUES (?,?,?,?,?)');
$log->execute(array($jobId, $queueId ?: null, $now, $result, $detail));

if ($queueId) {
  $u = $dbh->prepare("UPDATE recurringwakeup_queue SET status='completed', processed_at=? WHERE id=?");
  $u->execute(array($now, $queueId));
}

if ($result === 'confirmed') {
  rw_telegram_event($dbh, $job, $attempt, 'confirmed', 'Der Weckruf wurde mit Taste 1 bestätigt.');
  exit(0);
}

if ($result === 'snooze') {
  $minutes = max(1, (int)$job['snooze_minutes']);
  $due = date('Y-m-d H:i:s', time() + ($minutes * 60));
  $q = $dbh->prepare("INSERT INTO recurringwakeup_queue (job_id,due_at,attempt,reason,status,created_at) VALUES (?,?,?,?, 'pending', ?)");
  $q->execute(array($jobId, $due, $attempt + 1, 'snooze', $now));
  rw_telegram_event($dbh, $job, $attempt, 'snooze', 'Nächster Anruf: ' . date('d.m.Y H:i', strtotime($due)) . ' Uhr');
  exit(0);
}

if ($result === 'noinput' || $result === 'hangup') {
  if ($attempt < (int)$job['max_attempts']) {
    $minutes = max(1, (int)$job['retry_minutes']);
    $due = date('Y-m-d H:i:s', time() + ($minutes * 60));
    $q = $dbh->prepare("INSERT INTO recurringwakeup_queue (job_id,due_at,attempt,reason,status,created_at) VALUES (?,?,?,?, 'pending', ?)");
    $q->execute(array($jobId, $due, $attempt + 1, $result, $now));
    rw_telegram_event($dbh, $job, $attempt, $result, 'Nächster Versuch: ' . date('d.m.Y H:i', strtotime($due)) . ' Uhr');
  } else {
    $log->execute(array($jobId, $queueId ?: null, $now, 'failed', 'Maximale Versuchszahl erreicht'));
    rw_telegram_event($dbh, $job, $attempt, 'failed', 'Grund: Maximale Versuchszahl erreicht.');
  }
}
exit(0);
