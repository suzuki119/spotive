<?php

/**
 * lib/notifier.php
 * メール・SMS の送信。
 *   mail_driver = 'log'  … 送信せず storage/notify.log に書く（既定。開発用）
 *                 'smtp' … config/app.php の smtp 設定で実送信する
 *                 'mail' … PHP の mail() を使う（ローカルからはほぼ届かない）
 *   sms_driver  = 'log'  … 現時点ではログのみ。SMS 認証は後回しにしている。
 */

declare(strict_types=1);

require_once __DIR__ . '/support.php';

function notify_mail(string $to, string $subject, string $body): void
{
  $driver   = (string) config('notify.mail_driver', 'log');
  $from     = (string) config('notify.mail_from', 'no-reply@example.com');
  $fromName = (string) config('notify.mail_from_name', 'SPOTIVE');

  if ($driver === 'log') {
    notify_log("MAIL to={$to} subject={$subject}\n{$body}");
    return;
  }

  try {
    if ($driver === 'smtp') {
      smtp_send($from, $fromName, $to, $subject, $body);
    } else {
      $headers = 'From: ' . $fromName . ' <' . $from . ">\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n";
      if (!@mail($to, $subject, $body, $headers)) {
        throw new AppError('mail() が失敗しました。');
      }
    }
    notify_log("MAIL(sent via {$driver}) to={$to} subject={$subject}");
  } catch (Throwable $e) {
    // 送信に失敗しても登録処理そのものは止めない。
    // 本文をログに残すので、届かなくても手動で確認・再送できる
    notify_log("MAIL(FAILED: {$e->getMessage()}) to={$to} subject={$subject}\n{$body}");
    error_log('[SPOTIVE] mail failed: ' . $e->getMessage());
  }
}

function notify_sms(string $phoneE164, string $body): void
{
  if (config('notify.sms_driver', 'log') === 'log') {
    notify_log("SMS to={$phoneE164} body={$body}");
    return;
  }
  // TODO: SMS ゲートウェイ（Twilio など）の実装。
  // SMS 認証を有効にするときは、ここと lib/account.php の recalc_trust_level() の
  // Lv.1 の条件をあわせて戻すこと。
  notify_log("SMS(undelivered) to={$phoneE164} body={$body}");
}

/**
 * 開発用の送信ログ。
 * 添付書類と同じ storage.path（公開ディレクトリの外）に書く。
 * メール本文には登録リンクが載るので、ブラウザから読める場所に置かないこと。
 */
function notify_log(string $line): void
{
  $dir = (string) config('storage.path');
  if (!is_dir($dir) && !@mkdir($dir, 0770, true)) {
    error_log('[SPOTIVE] notify.log を書けません: ' . $dir);
    return;
  }
  @file_put_contents($dir . '/notify.log', '[' . date('Y-m-d H:i:s') . "] {$line}\n\n", FILE_APPEND | LOCK_EX);
  @chmod($dir . '/notify.log', 0600);
}

/**
 * 最小限の SMTP 送信。ライブラリを足さずに済ませるため自前で書いている。
 * 認証は AUTH LOGIN のみ。Gmail の場合は「アプリパスワード」を使うこと。
 */
function smtp_send(string $from, string $fromName, string $to, string $subject, string $body): void
{
  $host = (string) config('notify.smtp.host', '');
  $port = (int) config('notify.smtp.port', 587);
  $user = (string) config('notify.smtp.username', '');
  $pass = (string) config('notify.smtp.password', '');
  $enc  = (string) config('notify.smtp.encryption', 'tls');

  if ($host === '') {
    throw new AppError('SMTP の接続先が設定されていません。');
  }

  $target = ($enc === 'ssl' ? 'ssl://' : '') . $host;
  $socket = @fsockopen($target, $port, $errno, $errstr, 10);
  if ($socket === false) {
    throw new AppError("SMTP に接続できません（{$errstr}）。");
  }
  stream_set_timeout($socket, 10);

  $read = static function () use ($socket): string {
    $out = '';
    while (($line = fgets($socket, 515)) !== false) {
      $out .= $line;
      if (strlen($line) < 4 || $line[3] !== '-') {   // 「250-」は続き、「250 」で終わり
        break;
      }
    }
    return $out;
  };
  $send = static function (string $cmd, string $expect) use ($socket, $read): void {
    fwrite($socket, $cmd . "\r\n");
    $res = $read();
    if (!str_starts_with($res, $expect)) {
      throw new AppError('SMTP エラー: ' . trim($res));
    }
  };

  try {
    $read();
    $send('EHLO spotive.local', '250');

    if ($enc === 'tls') {
      $send('STARTTLS', '220');
      if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
        throw new AppError('STARTTLS に失敗しました。');
      }
      $send('EHLO spotive.local', '250');
    }

    if ($user !== '') {
      $send('AUTH LOGIN', '334');
      $send(base64_encode($user), '334');
      $send(base64_encode($pass), '235');
    }

    $send('MAIL FROM:<' . $from . '>', '250');
    $send('RCPT TO:<' . $to . '>', '250');
    $send('DATA', '354');

    $headers = 'From: ' . mime_header($fromName) . ' <' . $from . ">\r\n"
      . 'To: <' . $to . ">\r\n"
      . 'Subject: ' . mime_header($subject) . "\r\n"
      . 'Date: ' . date(DATE_RFC2822) . "\r\n"
      . "MIME-Version: 1.0\r\n"
      . "Content-Type: text/plain; charset=UTF-8\r\n"
      . "Content-Transfer-Encoding: base64\r\n";

    // 行頭の「.」は SMTP では本文の終わりと解釈されるため、base64 にして避ける
    $send($headers . "\r\n" . chunk_split(base64_encode($body), 76, "\r\n") . '.', '250');
    $send('QUIT', '221');
  } finally {
    fclose($socket);
  }
}

/** 日本語の件名・表示名を MIME エンコードする */
function mime_header(string $text): string
{
  return '=?UTF-8?B?' . base64_encode($text) . '?=';
}
