<?php

/**
 * tools/create-admin.php
 * 審査画面を開ける運営アカウントを作る。ブラウザからは実行できない。
 *
 *   php tools/create-admin.php admin@example.com 09000000000 'パスワード' [admin|reviewer]
 *
 * 既にあるアカウントを指定した場合は、役割だけを上げる。
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
  http_response_code(404);
  exit;
}

require_once __DIR__ . '/../lib/account.php';

[$script, $email, $phone, $password, $role] = $argv + [null, null, null, null, 'admin'];

if ($email === null || $phone === null || $password === null) {
  fwrite(STDERR, "使い方: php tools/create-admin.php <メール> <電話> <パスワード> [admin|reviewer]\n");
  exit(1);
}
if (!in_array($role, ['admin', 'reviewer'], true)) {
  fwrite(STDERR, "役割は admin か reviewer を指定してください。\n");
  exit(1);
}

$normalizedEmail = strtolower(trim($email));
$normalizedPhone = Validator::normalizePhone($phone);
if ($normalizedPhone === null) {
  fwrite(STDERR, "電話番号は SMS を受信できる携帯番号を指定してください。\n");
  exit(1);
}

try {
  $existing = db_one('SELECT id FROM users WHERE email_normalized = :e', ['e' => $normalizedEmail]);

  if ($existing !== null) {
    db_update('users', ['role' => $role], 'id = :id', ['id' => $existing['id']]);
    echo "既存のアカウント #{$existing['id']} を {$role} にしました。\n";
    exit(0);
  }

  $userId = db_insert('users', [
    'email'             => trim($email),
    'email_normalized'  => $normalizedEmail,
    'email_verified_at' => date('Y-m-d H:i:s'),
    'phone_e164'        => $normalizedPhone,
    'password_hash'     => hash_password($password),
    'nickname'          => '運営',
    'birthdate'         => '1990-01-01',
    'trust_level'       => LEVEL_USER,
    'status'            => 'active',
    'role'              => $role,
  ]);

  audit_log($userId, 'user.create_admin', 'user', $userId, ['role' => $role]);
  echo "{$role} アカウント #{$userId} を作成しました（{$email}）。\n";
} catch (Throwable $e) {
  fwrite(STDERR, 'エラー: ' . $e->getMessage() . "\n");
  exit(1);
}
