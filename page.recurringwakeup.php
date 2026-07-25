<?php
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }
$dbh = FreePBX::Database();
require_once __DIR__ . '/lib.php';
function rw_h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function rw_time($v) { return ($v && $v !== '00:00:00') ? substr($v,0,5) : ''; }
$days = array('mon'=>'Montag','tue'=>'Dienstag','wed'=>'Mittwoch','thu'=>'Donnerstag','fri'=>'Freitag','sat'=>'Samstag','sun'=>'Sonntag');
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = isset($_POST['rw_action']) ? $_POST['rw_action'] : '';
  if ($action === 'save') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $name = trim(isset($_POST['name']) ? $_POST['name'] : '');
    $target = preg_replace('/[^0-9]/', '', isset($_POST['target']) ? $_POST['target'] : '');
    $mode = (isset($_POST['time_mode']) && $_POST['time_mode'] === 'individual') ? 'individual' : 'common';
    if ($name === '' || !preg_match('/^[0-9]{2,20}$/', $target)) {
      $msg = '<div class="alert alert-danger">Bitte Bezeichnung und eine gültige Rufnummer mit 2 bis 20 Ziffern eingeben.</div>';
    } else {
      $vals = array();
      $commonTime = !empty($_POST['common_time']) ? $_POST['common_time'] : null;
      foreach ($days as $k=>$label) {
        $individual = !empty($_POST[$k.'_time']) ? $_POST[$k.'_time'] : $commonTime;
        $vals[$k] = !empty($_POST['day_'.$k]) ? ($individual ?: null) : null;
      }
      $now = date('Y-m-d H:i:s');
      $params = array(
        $name,$target,!empty($_POST['enabled'])?1:0,$mode,$commonTime,
        $vals['mon'],$vals['tue'],$vals['wed'],$vals['thu'],$vals['fri'],$vals['sat'],$vals['sun'],
        max(15,min(300,(int)$_POST['ring_seconds'])),max(1,min(10,(int)$_POST['max_attempts'])),
        max(1,min(120,(int)$_POST['retry_minutes'])),max(1,min(60,(int)$_POST['snooze_minutes'])),
        trim($_POST['callerid']) ?: 'Weckruf <*68>',$now
      );
      if ($id) {
        $sql = "UPDATE recurringwakeup_jobs SET name=?,target=?,enabled=?,time_mode=?,common_time=?,mon_time=?,tue_time=?,wed_time=?,thu_time=?,fri_time=?,sat_time=?,sun_time=?,ring_seconds=?,max_attempts=?,retry_minutes=?,snooze_minutes=?,callerid=?,updated_at=? WHERE id=?";
        $params[] = $id;
      } else {
        $sql = "INSERT INTO recurringwakeup_jobs (name,target,enabled,time_mode,common_time,mon_time,tue_time,wed_time,thu_time,fri_time,sat_time,sun_time,ring_seconds,max_attempts,retry_minutes,snooze_minutes,callerid,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
        array_splice($params, 17, 0, array($now));
      }
      $dbh->prepare($sql)->execute($params);
      $msg = '<div class="alert alert-success">Eintrag gespeichert.</div>';
    }
  } elseif ($action === 'toggle') {
    $id=(int)$_POST['id'];
    $dbh->prepare('UPDATE recurringwakeup_jobs SET enabled=1-enabled,updated_at=? WHERE id=?')->execute(array(date('Y-m-d H:i:s'),$id));
  } elseif ($action === 'delete') {
    $id=(int)$_POST['id'];
    $dbh->prepare('DELETE FROM recurringwakeup_jobs WHERE id=?')->execute(array($id));
  } elseif ($action === 'test') {
    $id=(int)$_POST['id']; $now=date('Y-m-d H:i:s');
    $dbh->prepare("INSERT INTO recurringwakeup_queue (job_id,due_at,attempt,reason,status,created_at) VALUES (?,?,1,'test','pending',?)")->execute(array($id,$now,$now));
    $msg='<div class="alert alert-info">Testanruf wurde eingeplant und startet spätestens innerhalb einer Minute.</div>';
  } elseif ($action === 'save_telegram') {
    $enabled = !empty($_POST['telegram_enabled']) ? '1' : '0';
    $chatId = trim(isset($_POST['telegram_chat_id']) ? $_POST['telegram_chat_id'] : '');
    $token = trim(isset($_POST['telegram_bot_token']) ? $_POST['telegram_bot_token'] : '');
    rw_set_setting($dbh, 'telegram_enabled', $enabled);
    rw_set_setting($dbh, 'telegram_chat_id', $chatId);
    if ($token !== '') { rw_set_setting($dbh, 'telegram_bot_token', $token); }
    $msg='<div class="alert alert-success">Telegram-Einstellungen gespeichert.</div>';
  } elseif ($action === 'test_telegram') {
    $error = '';
    if (rw_telegram_send($dbh, "✅ Testnachricht von FreePBX\n\nDas Modul Wiederkehrende Weckrufe kann Telegram-Nachrichten senden.", $error)) {
      $msg='<div class="alert alert-success">Telegram-Testnachricht wurde gesendet.</div>';
    } else {
      $msg='<div class="alert alert-danger">Telegram-Test fehlgeschlagen: '.rw_h($error).'</div>';
    }
  }
}

$settings = rw_get_settings($dbh);
$edit = null;
if (!empty($_GET['edit'])) {
  $s=$dbh->prepare('SELECT * FROM recurringwakeup_jobs WHERE id=?');
  $s->execute(array((int)$_GET['edit']));
  $edit=$s->fetch(PDO::FETCH_ASSOC);
}
$jobs = $dbh->query('SELECT * FROM recurringwakeup_jobs ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
$logs = $dbh->query('SELECT l.*,j.name FROM recurringwakeup_log l LEFT JOIN recurringwakeup_jobs j ON j.id=l.job_id ORDER BY l.id DESC LIMIT 30')->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="container-fluid">
<h1>Wiederkehrende Weckrufe</h1>
<p>Externe Rufnummern werden über die normalen FreePBX-Ausgangsrouten gewählt. Taste <strong>1</strong> bestätigt den Weckruf, Taste <strong>5</strong> plant einen weiteren Anruf nach der eingestellten Schlummerzeit. Auch vorzeitiges Auflegen ohne Bestätigung löst einen weiteren Versuch aus.</p>
<?php echo $msg; ?>

<div class="panel panel-default"><div class="panel-heading"><strong>Telegram-Benachrichtigungen</strong></div><div class="panel-body">
<form method="post"><input type="hidden" name="rw_action" value="save_telegram">
<div class="row">
<div class="col-md-2"><label>Status</label><div><label><input type="checkbox" name="telegram_enabled" value="1" <?php echo $settings['telegram_enabled']==='1'?'checked':''; ?>> Telegram aktivieren</label></div></div>
<div class="col-md-5"><label>Telegram-Bot-Token</label><input class="form-control" type="password" name="telegram_bot_token" autocomplete="new-password" placeholder="Leer lassen, um gespeicherten Token beizubehalten"><small>Vollständiger Token von BotFather, zum Beispiel 123456789:AA...</small></div>
<div class="col-md-5"><label>Telegram-Chat-ID</label><input class="form-control" name="telegram_chat_id" value="<?php echo rw_h($settings['telegram_chat_id']); ?>" placeholder="Zum Beispiel -1001234567890"></div>
</div><br>
<button class="btn btn-primary" type="submit">Telegram-Einstellungen speichern</button>
</form>
<form method="post" style="display:inline-block;margin-top:10px"><input type="hidden" name="rw_action" value="test_telegram"><button class="btn btn-info" type="submit">Telegram-Testnachricht senden</button></form>
<?php if ($settings['telegram_bot_token'] !== ''): ?><span class="label label-success" style="margin-left:10px">Bot-Token gespeichert</span><?php endif; ?>
</div></div>

<div class="panel panel-default"><div class="panel-heading"><strong><?php echo $edit?'Weckruf bearbeiten':'Neuen Weckruf anlegen'; ?></strong></div><div class="panel-body">
<form method="post"><input type="hidden" name="rw_action" value="save"><input type="hidden" name="id" value="<?php echo $edit?(int)$edit['id']:0; ?>">
<div class="row"><div class="col-md-4"><label>Bezeichnung</label><input class="form-control" required name="name" value="<?php echo rw_h($edit?$edit['name']:''); ?>"></div>
<div class="col-md-4"><label>Zielrufnummer/Nebenstelle</label><input class="form-control" required name="target" pattern="[0-9]{2,20}" value="<?php echo rw_h($edit?$edit['target']:''); ?>"><small>Zum Beispiel 01701234567 oder 201</small></div>
<div class="col-md-2"><label>Status</label><div><label><input type="checkbox" name="enabled" value="1" <?php echo (!$edit||$edit['enabled'])?'checked':''; ?>> aktiv</label></div></div></div><hr>
<div class="row"><div class="col-md-4"><label>Zeitmodus</label><select class="form-control" name="time_mode" id="time_mode"><option value="common" <?php echo (!$edit||$edit['time_mode']==='common')?'selected':''; ?>>Eine Uhrzeit für alle Tage</option><option value="individual" <?php echo ($edit&&$edit['time_mode']==='individual')?'selected':''; ?>>Unterschiedliche Uhrzeiten</option></select></div>
<div class="col-md-3"><label>Gemeinsame Uhrzeit</label><input class="form-control" type="time" name="common_time" value="<?php echo rw_h($edit?rw_time($edit['common_time']):'06:00'); ?>"></div></div><br>
<table class="table table-condensed" style="max-width:650px"><thead><tr><th>Tag</th><th>aktiv</th><th>individuelle Uhrzeit</th></tr></thead><tbody>
<?php foreach($days as $k=>$label): $active=$edit&&!empty($edit[$k.'_time']); ?>
<tr><td><?php echo $label; ?></td><td><input type="checkbox" name="day_<?php echo $k; ?>" value="1" <?php echo $active?'checked':''; ?>></td><td><input class="form-control" style="max-width:160px" type="time" name="<?php echo $k; ?>_time" value="<?php echo rw_h($edit?rw_time($edit[$k.'_time']):''); ?>"></td></tr>
<?php endforeach; ?></tbody></table>
<div class="row"><div class="col-md-2"><label>Klingeldauer (Sek.)</label><input class="form-control" type="number" min="15" max="300" name="ring_seconds" value="<?php echo (int)($edit?$edit['ring_seconds']:60); ?>"></div>
<div class="col-md-2"><label>Max. Versuche</label><input class="form-control" type="number" min="1" max="10" name="max_attempts" value="<?php echo (int)($edit?$edit['max_attempts']:3); ?>"></div>
<div class="col-md-2"><label>Wiederholung (Min.)</label><input class="form-control" type="number" min="1" max="120" name="retry_minutes" value="<?php echo (int)($edit?$edit['retry_minutes']:10); ?>"></div>
<div class="col-md-2"><label>Taste 5 (Min.)</label><input class="form-control" type="number" min="1" max="60" name="snooze_minutes" value="<?php echo (int)($edit?$edit['snooze_minutes']:5); ?>"></div>
<div class="col-md-4"><label>Anruferkennung</label><input class="form-control" name="callerid" value="<?php echo rw_h($edit?$edit['callerid']:'Weckruf <*68>'); ?>"></div></div><br>
<button class="btn btn-primary" type="submit">Speichern</button> <?php if($edit): ?><a class="btn btn-default" href="config.php?display=recurringwakeup">Abbrechen</a><?php endif; ?>
</form></div></div>

<div class="panel panel-default"><div class="panel-heading"><strong>Vorhandene Weckrufe</strong></div><div class="panel-body"><table class="table table-striped"><thead><tr><th>Name</th><th>Ziel</th><th>Tage/Zeiten</th><th>Status</th><th>Aktionen</th></tr></thead><tbody>
<?php foreach($jobs as $j): ?><tr><td><?php echo rw_h($j['name']); ?></td><td><?php echo rw_h($j['target']); ?></td><td><?php $x=array(); foreach($days as $k=>$label){if($j[$k.'_time']){$t=$j['time_mode']==='common'?rw_time($j['common_time']):rw_time($j[$k.'_time']);$x[]=substr($label,0,2).' '.$t;}} echo rw_h(implode(', ',$x)); ?></td><td><?php echo $j['enabled']?'<span class="label label-success">Aktiv</span>':'<span class="label label-warning">Pausiert</span>'; ?></td><td>
<a class="btn btn-xs btn-default" href="config.php?display=recurringwakeup&edit=<?php echo (int)$j['id']; ?>">Bearbeiten</a>
<form method="post" style="display:inline"><input type="hidden" name="rw_action" value="toggle"><input type="hidden" name="id" value="<?php echo (int)$j['id']; ?>"><button class="btn btn-xs btn-warning"><?php echo $j['enabled']?'Pausieren':'Aktivieren'; ?></button></form>
<form method="post" style="display:inline"><input type="hidden" name="rw_action" value="test"><input type="hidden" name="id" value="<?php echo (int)$j['id']; ?>"><button class="btn btn-xs btn-info">Jetzt testen</button></form>
<form method="post" style="display:inline" onsubmit="return confirm('Wirklich löschen?')"><input type="hidden" name="rw_action" value="delete"><input type="hidden" name="id" value="<?php echo (int)$j['id']; ?>"><button class="btn btn-xs btn-danger">Löschen</button></form></td></tr><?php endforeach; ?>
<?php if(!$jobs): ?><tr><td colspan="5">Noch keine Einträge vorhanden.</td></tr><?php endif; ?></tbody></table></div></div>

<div class="panel panel-default"><div class="panel-heading"><strong>Letzte Ereignisse</strong></div><div class="panel-body"><table class="table table-condensed"><thead><tr><th>Zeit</th><th>Name</th><th>Status</th><th>Details</th></tr></thead><tbody><?php foreach($logs as $l): ?><tr><td><?php echo rw_h($l['event_time']); ?></td><td><?php echo rw_h($l['name']); ?></td><td><?php echo rw_h($l['status']); ?></td><td><?php echo rw_h($l['detail']); ?></td></tr><?php endforeach; ?></tbody></table></div></div>
</div>
