<?php

/**
 * lib/account-delete.php
 * アカウントの削除（退会）。pages/setting/account-delete.php から使う。
 *
 * users の行は消さずに status = 'deleted' にし、個人情報を消した値に置き換える。
 *   行を消さない理由 … 大会・審査・監査ログ・添付書類が users を参照していて、消すと記録が壊れるため
 *   置き換える理由   … 本人が特定できる情報を残さないため。メールアドレスと電話番号の重複チェック
 *                      （UNIQUE）からも外れるので、同じメールアドレスで登録し直せる
 *
 * 公開中・審査中の大会や、審査中の主催者申請があるあいだは削除させない
 * （参加者や審査に影響するので、先に中止・取り下げをしてもらう）。
 */

declare(strict_types=1);

require_once __DIR__ . '/account.php';

/** 削除したユーザーの表示名（過去の大会の主催者名などに出る） */
const ACCOUNT_DELETED_NICKNAME = '退会したユーザー';

/** パスワードを確かめたあと、最後の確認画面で削除できる時間（秒） */
const ACCOUNT_DELETE_CONFIRM_TTL = 600;

/**
 * 今は削除できない理由。空なら削除できる。
 *
 * @return list<string>
 */
function account_delete_blockers(array $user): array
{
  $userId   = (int) $user['id'];
  $blockers = [];

  if (in_array((string) $user['role'], ['reviewer', 'admin'], true)) {
    $blockers[] = '運営のアカウントは、この画面からは削除できません。ほかの管理者に依頼してください。';
  }

  $tournaments = (int) db_one(
    'SELECT COUNT(*) AS c FROM tournaments
      WHERE organizer_user_id = :u
        AND status IN ("submitted", "published")
        AND ends_at >= NOW()',
    ['u' => $userId]
  )['c'];
  if ($tournaments > 0) {
    $blockers[] = "公開中・審査中の大会が {$tournaments} 件あります。先に大会を中止するか、終わるのを待ってください。";
  }

  $applications = (int) db_one(
    'SELECT COUNT(*) AS c FROM organizer_applications
      WHERE user_id = :u AND status IN ("submitted", "in_review")',
    ['u' => $userId]
  )['c'];
  if ($applications > 0) {
    $blockers[] = '審査中の主催者申請があります。審査が終わってから削除してください。';
  }

  return $blockers;
}

/**
 * 1 回目の確認。パスワードを確かめる。
 * 総当たりを防ぐため、1 人あたり 15 分に 5 回までにする。
 */
function account_delete_check_password(array $user, string $password): void
{
  rate_limit_hit('account_delete:user:' . (int) $user['id'], 5, 900);

  $row = db_one('SELECT password_hash FROM users WHERE id = :id', ['id' => $user['id']]);
  if ($row === null || $password === '' || !password_verify($password, (string) $row['password_hash'])) {
    throw new AppError('入力内容を確認してください。', ['password' => 'パスワードが違います。']);
  }
}

/**
 * 削除する。取り消しはできない。
 * 終わったらこのユーザーのセッションはすべて無効になる（呼び出し側でログアウトもすること）。
 */
function account_delete(array $user): void
{
  $userId = (int) $user['id'];

  db_transaction(static function () use ($userId): void {
    // 本人が特定できる値を、消した値に置き換える。ログインもできなくなる
    db_update('users', [
      'email'              => "deleted-{$userId}@deleted.invalid",
      'email_normalized'   => "deleted-{$userId}@deleted.invalid",
      'email_verified_at'  => null,
      'phone_e164'         => "deleted-{$userId}",     // UNIQUE なので ID で重ならないようにする
      'phone_verified_at'  => null,
      'password_hash'      => hash_password(bin2hex(random_bytes(32))),   // 誰も知らないパスワード
      'nickname'           => ACCOUNT_DELETED_NICKNAME,
      'birthdate'          => '1900-01-01',
      'trust_level'        => 0,
      'status'             => 'deleted',
      'failed_login_count' => 0,
      'locked_until'       => null,
      'deleted_at'         => date('Y-m-d H:i:s'),
    ], 'id = :id', ['id' => $userId]);

    // どの端末からもログアウトさせる
    db_run(
      'UPDATE auth_sessions SET revoked_at = NOW() WHERE user_id = :u AND revoked_at IS NULL',
      ['u' => $userId]
    );

    // 本人のためだけのデータは消す
    foreach (['favorite_teams', 'notification_reads', 'email_verifications', 'phone_verifications', 'travel_plans'] as $table) {
      db_run("DELETE FROM {$table} WHERE user_id = :u", ['u' => $userId]);   // テーブル名はコード内の固定値
    }

    // 本人確認で預かった氏名・生年月日は消し、書類は次の削除バッチで消えるようにする
    db_run(
      'UPDATE identity_verifications
          SET legal_name = NULL, legal_name_kana = NULL, birthdate = NULL, result_payload = NULL
        WHERE user_id = :u',
      ['u' => $userId]
    );
    db_run(
      'UPDATE attachments SET purge_after = NOW()
        WHERE owner_type = "identity"
          AND owner_id IN (SELECT id FROM identity_verifications WHERE user_id = :u)
          AND deleted_at IS NULL',
      ['u' => $userId]
    );

    // 主催者の資格を取り消し、下書き中の申請は取り下げにする
    db_run(
      'UPDATE organizer_profiles SET status = "revoked", suspend_reason = "退会" WHERE user_id = :u',
      ['u' => $userId]
    );
    db_run(
      'UPDATE organizer_applications SET status = "withdrawn"
        WHERE user_id = :u AND status IN ("draft", "more_info_required")',
      ['u' => $userId]
    );

    // 下書きの大会は、もう公開できないので中止にしておく
    db_run(
      'UPDATE tournaments SET status = "cancelled" WHERE organizer_user_id = :u AND status = "draft"',
      ['u' => $userId]
    );

    audit_log($userId, 'user.delete', 'user', $userId);
  });
}
