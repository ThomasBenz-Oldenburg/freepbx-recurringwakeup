<?php
if (!function_exists('rw_get_settings')) {
  function rw_get_settings($dbh) {
    $defaults = array(
      'telegram_enabled' => '0',
      'telegram_bot_token' => '',
      'telegram_chat_id' => ''
    );
    try {
      $rows = $dbh->query("SELECT setting_key, setting_value FROM recurringwakeup_settings")->fetchAll(PDO::FETCH_ASSOC);
      foreach ($rows as $row) {
        $defaults[$row['setting_key']] = (string)$row['setting_value'];
      }
    } catch (Exception $e) {
      // Table may not exist yet during an incomplete upgrade.
    }
    return $defaults;
  }

  function rw_set_setting($dbh, $key, $value) {
    $stmt = $dbh->prepare("INSERT INTO recurringwakeup_settings (setting_key, setting_value, updated_at) VALUES (?,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), updated_at=VALUES(updated_at)");
    $stmt->execute(array($key, (string)$value, date('Y-m-d H:i:s')));
  }

  function rw_telegram_send($dbh, $text, &$error) {
    $error = '';
    $settings = rw_get_settings($dbh);
    if ($settings['telegram_enabled'] !== '1') {
      $error = 'Telegram ist deaktiviert.';
      return false;
    }
    $token = trim($settings['telegram_bot_token']);
    $chatId = trim($settings['telegram_chat_id']);
    if ($token === '' || $chatId === '') {
      $error = 'Bot-Token oder Chat-ID fehlt.';
      return false;
    }
    if (!preg_match('/^[0-9]+:[A-Za-z0-9_-]+$/', $token)) {
      $error = 'Der Bot-Token hat kein gültiges Format.';
      return false;
    }
    if (!preg_match('/^-?[0-9]+$/', $chatId)) {
      $error = 'Die Chat-ID hat kein gültiges Format.';
      return false;
    }

    $url = 'https://api.telegram.org/bot' . $token . '/sendMessage';
    $post = http_build_query(array(
      'chat_id' => $chatId,
      'text' => $text,
      'disable_web_page_preview' => 'true'
    ), '', '&');

    $body = false;
    $httpCode = 0;
    if (function_exists('curl_init')) {
      $ch = curl_init($url);
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
      curl_setopt($ch, CURLOPT_TIMEOUT, 15);
      curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
      $body = curl_exec($ch);
      $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
      if ($body === false) { $error = curl_error($ch); }
      curl_close($ch);
    } else {
      $context = stream_context_create(array('http' => array(
        'method' => 'POST',
        'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => $post,
        'timeout' => 15,
        'ignore_errors' => true
      )));
      $body = @file_get_contents($url, false, $context);
      if (isset($http_response_header[0]) && preg_match('/\s([0-9]{3})\s/', $http_response_header[0], $m)) {
        $httpCode = (int)$m[1];
      }
    }

    if ($body === false || $body === '') {
      if ($error === '') { $error = 'Keine Antwort von Telegram.'; }
      return false;
    }
    $json = json_decode($body, true);
    if ($httpCode >= 200 && $httpCode < 300 && is_array($json) && !empty($json['ok'])) {
      return true;
    }
    if (is_array($json) && !empty($json['description'])) {
      $error = $json['description'];
    } elseif ($error === '') {
      $error = 'Telegram-HTTP-Fehler ' . $httpCode;
    }
    return false;
  }

  function rw_mask_target($target) {
    $target = (string)$target;
    $len = strlen($target);
    if ($len <= 5) { return $target; }
    return substr($target, 0, 4) . str_repeat('*', max(2, $len - 6)) . substr($target, -2);
  }

  function rw_telegram_event($dbh, $job, $attempt, $status, $extra) {
    $labels = array(
      'confirmed' => "✅ Weckruf bestätigt",
      'snooze' => "😴 Schlummerfunktion aktiviert",
      'noinput' => "⚠️ Weckruf nicht bestätigt",
      'hangup' => "⚠️ Aufgelegt ohne Bestätigung",
      'failed' => "❌ Weckruf endgültig fehlgeschlagen"
    );
    if (!isset($labels[$status])) { return; }
    $lines = array(
      $labels[$status],
      '',
      'Name: ' . $job['name'],
      'Ziel: ' . rw_mask_target($job['target']),
      'Zeit: ' . date('d.m.Y H:i:s'),
      'Versuch: ' . (int)$attempt . ' von ' . (int)$job['max_attempts']
    );
    if ($extra !== '') { $lines[] = $extra; }
    $error = '';
    rw_telegram_send($dbh, implode("\n", $lines), $error);
  }
}
