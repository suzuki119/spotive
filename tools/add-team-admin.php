<?php

/**
 * tools/add-team-admin.php
 * チームの管理者を登録する（team_admins に行を足す）。ブラウザからは実行できない。
 * 登録されたユーザーは、管理者画面（pages/administrators/administrators.php）でそのチームの数字を見られる。
 *
 *   php tools/add-team-admin.php <メール> <チームID> <表示名> [owner|editor]
 *   例：php tools/add-team-admin.php tubc@example.com team-001 'TUBC事務局' owner
 *
 * チーム ID は data/teams.json の id。同じユーザーとチームの組が既にあれば、表示名と役割を上書きする。
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
  http_response_code(404);
  exit;
}

require_once __DIR__ . '/../lib/match.php';
require_once __DIR__ . '/../lib/audit.php';

[$script, $email, $teamId, $displayName, $role] = $argv + [null, null, null, null, 'editor'];

if ($email === null || $teamId === null || $displayName === null || trim($displayName) === '') {
  fwrite(STDERR, "使い方: php tools/add-team-admin.php <メール> <チームID> <表示名> [owner|editor]\n");
  exit(1);
}
if (!in_array($role, ['owner', 'editor'], true)) {
  fwrite(STDERR, "役割は owner か editor を指定してください。\n");
  exit(1);
}
if (!isset(load_teams()[$teamId])) {
  fwrite(STDERR, "チーム {$teamId} は data/teams.json にありません。\n");
  exit(1);
}
if (mb_strlen($displayName) > 120) {
  fwrite(STDERR, "表示名は 120 文字以内にしてください。\n");
  exit(1);
}

try {
  $user = db_one('SELECT id FROM users WHERE email_normalized = :e', ['e' => strtolower(trim($email))]);
  if ($user === null) {
    fwrite(STDERR, "このメールアドレスのアカウントはありません。先に会員登録してください。\n");
    exit(1);
  }

  db_run(
    'INSERT INTO team_admins (user_id, team_id, display_name, role)
     VALUES (:u, :t, :name, :role)
     ON DUPLICATE KEY UPDATE display_name = VALUES(display_name), role = VALUES(role)',
    ['u' => $user['id'], 't' => $teamId, 'name' => trim($displayName), 'role' => $role]
  );

  audit_log(null, 'team_admin.add', 'user', (int) $user['id'], ['team_id' => $teamId, 'role' => $role]);
  echo "アカウント #{$user['id']} を {$teamId} の管理者（{$role}）にしました。\n";
} catch (Throwable $e) {
  fwrite(STDERR, 'エラー: ' . $e->getMessage() . "\n");
  exit(1);
}
