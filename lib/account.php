<?php

/**
 * lib/account.php
 * Lv.1 一般ユーザー。
 *   メールアドレスだけで登録を始め、届いたリンクから残りを入力して完成させる。
 *   氏名・本人確認書類はここでは一切求めない（Lv.2 の話）。
 */

declare(strict_types=1);

require_once __DIR__ . '/support.php';
require_once __DIR__ . '/validator.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/ratelimit.php';
require_once __DIR__ . '/notifier.php';

/**
 * 登録の 1 段階目。メールアドレスだけ受け取り、確認リンクを送る。
 * この時点では users の行を作らない（捨てアドレスで空アカウントを増やさないため）。
 * 登録済みかどうかは画面では区別しない（アカウントの有無を知られないため）。
 */
function account_start_signup(array $in): void
{
  (new Validator($in))->required('email', 'メールアドレス')->email('email')->validate();

  $email = strtolower(trim((string) $in['email']));

  rate_limit_hit('signup_start:ip:' . rate_limit_ip(), 10, 3600);
  rate_limit_hit('signup_start:mail:' . $email, 5, 3600);

  $exists = db_one(
    'SELECT id FROM users WHERE email_normalized = :e AND deleted_at IS NULL',
    ['e' => $email]
  );

  if ($exists !== null) {
    notify_mail(
      $email,
      '【SPOTIVE】登録済みのメールアドレスです',
      "このメールアドレスは既に登録されています。\n"
        . app_url('pages/account/login.php') . " からログインしてください。\n"
    );
    return;
  }

  $plain = bin2hex(random_bytes(32));
  $ttl   = (int) config('auth.email_token_ttl', 86400);

  // 前に送ったリンクは使えなくする
  db_run(
    'UPDATE email_verifications SET consumed_at = NOW()
      WHERE user_id IS NULL AND target_email = :e AND consumed_at IS NULL',
    ['e' => $email]
  );

  db_insert('email_verifications', [
    'user_id'      => null,
    'purpose'      => 'signup',
    'target_email' => $email,
    'token_hash'   => hash('sha256', $plain),   // 平文は保存しない
    'expires_at'   => date('Y-m-d H:i:s', time() + $ttl),
  ]);

  $link = app_url('pages/account/verify-email.php') . '?token=' . $plain;
  notify_mail(
    $email,
    '【SPOTIVE】アカウント登録のご案内',
    "以下のリンクを開いて、登録を続けてください（24時間有効）。\n{$link}\n"
  );
}

/**
 * 登録の 2 段階目。確認リンクのトークンと残りの情報を受け取り、アカウントを作る。
 * リンクを開いた本人なのでメールは確認済みとして作る＝この時点で Lv.1。
 *
 * @return int 作成したユーザー ID
 */
function account_complete_signup(string $token, array $in): int
{
  $pending = account_pending_signup($token);

  (new Validator($in))
    ->required('phone', '電話番号')->phone('phone')
    ->required('password', 'パスワード')->password('password')
    ->required('nickname', 'ニックネーム')->length('nickname', 'ニックネーム', 1, 50)
    ->required('birthdate', '生年月日')->date('birthdate', '生年月日')->minAge('birthdate', 13)
    ->accepted('agree_terms', '利用規約')
    ->accepted('agree_privacy', 'プライバシーポリシー')
    ->validate();

  $email    = strtolower(trim((string) $pending['target_email']));
  $phone    = Validator::normalizePhone((string) $in['phone']);
  $policies = account_current_policies();

  return db_transaction(static function () use ($pending, $email, $phone, $in, $policies): int {
    $dup = db_one(
      'SELECT id FROM users WHERE email_normalized = :e OR phone_e164 = :p',
      ['e' => $email, 'p' => $phone]
    );
    if ($dup !== null) {
      // 実在アカウントの列挙を防ぐため、どちらが重複かは明かさない
      throw new AppError('このメールアドレスまたは電話番号は既に登録されています。');
    }

    $userId = db_insert('users', [
      'email'             => (string) $pending['target_email'],
      'email_normalized'  => $email,
      'email_verified_at' => date('Y-m-d H:i:s'),
      'phone_e164'        => $phone,
      'password_hash'     => hash_password((string) $in['password']),
      'nickname'          => trim((string) $in['nickname']),
      'birthdate'         => (string) $in['birthdate'],
      'trust_level'       => LEVEL_USER,
      'status'            => 'active',
    ]);

    // いつ・どの版の規約に同意したかを残す
    foreach (['terms', 'privacy'] as $kind) {
      db_insert('user_agreements', [
        'user_id'    => $userId,
        'policy_id'  => $policies[$kind],
        'ip'         => request_ip_binary(),
        'user_agent' => request_user_agent(),
      ]);
    }

    db_run(
      'UPDATE email_verifications SET consumed_at = NOW(), user_id = :u WHERE id = :id',
      ['u' => $userId, 'id' => $pending['id']]
    );

    audit_log($userId, 'user.register', 'user', $userId, ['flow' => 'email_first']);
    return $userId;
  });
}

/** 有効な「登録待ち」トークンの行を返す。無効なら AppError */
function account_pending_signup(string $token): array
{
  if ($token === '') {
    throw new AppError('リンクが無効です。');
  }

  $row = db_one(
    'SELECT * FROM email_verifications
      WHERE token_hash = :h AND purpose = "signup" AND user_id IS NULL
        AND consumed_at IS NULL AND expires_at > NOW()',
    ['h' => hash('sha256', $token)]
  );
  if ($row === null) {
    throw new AppError('リンクが無効か、有効期限が切れています。お手数ですが、もう一度メールアドレスを入力してください。');
  }
  return $row;
}

/** 現行の規約・プライバシーポリシーの版 @return array{terms:int,privacy:int} */
function account_current_policies(): array
{
  $rows = db_all(
    'SELECT id, kind FROM policy_documents
      WHERE retired_at IS NULL AND published_at <= NOW()
      ORDER BY published_at DESC'
  );

  $current = [];
  foreach ($rows as $row) {
    $current[$row['kind']] ??= (int) $row['id'];
  }
  if (!isset($current['terms'], $current['privacy'])) {
    throw new AppError('規約が未設定です。db/seed.sql を流し込んでください。');
  }
  return $current;
}

/**
 * 既にアカウントがある人へ、メール確認リンクを送り直す。
 */
function account_send_email_verification(int $userId, string $email, string $purpose = 'signup'): void
{
  rate_limit_hit("mail:user:{$userId}", 5, 3600);

  $plain = bin2hex(random_bytes(32));
  $ttl   = (int) config('auth.email_token_ttl', 86400);

  db_run(
    'UPDATE email_verifications SET consumed_at = NOW()
      WHERE user_id = :u AND purpose = :p AND consumed_at IS NULL',
    ['u' => $userId, 'p' => $purpose]
  );

  db_insert('email_verifications', [
    'user_id'      => $userId,
    'purpose'      => $purpose,
    'target_email' => $email,
    'token_hash'   => hash('sha256', $plain),
    'expires_at'   => date('Y-m-d H:i:s', time() + $ttl),
  ]);

  $link = app_url('pages/account/verify-email.php') . '?token=' . $plain;
  notify_mail(
    $email,
    '【SPOTIVE】メールアドレスの確認',
    "以下のリンクを開いてメールアドレスを確認してください（24時間有効）。\n{$link}\n"
  );
}

/**
 * 既存アカウントのメール確認リンクを消化する。
 * 「登録待ち」のトークンだった場合は、続きの入力へ案内するため null を返す。
 */
function account_verify_email(string $token): ?int
{
  $hash = hash('sha256', $token);

  $row = db_one(
    'SELECT * FROM email_verifications
      WHERE token_hash = :h AND user_id IS NOT NULL
        AND consumed_at IS NULL AND expires_at > NOW()',
    ['h' => $hash]
  );
  if ($row === null) {
    return null;
  }

  return db_transaction(static function () use ($row): int {
    db_run('UPDATE email_verifications SET consumed_at = NOW() WHERE id = :id', ['id' => $row['id']]);
    db_run(
      'UPDATE users SET email_verified_at = NOW() WHERE id = :u AND email_verified_at IS NULL',
      ['u' => $row['user_id']]
    );
    recalc_trust_level((int) $row['user_id']);
    audit_log((int) $row['user_id'], 'user.email_verified', 'user', (int) $row['user_id']);
    return (int) $row['user_id'];
  });
}

/**
 * ログイン。成功したらユーザー行を返す。
 * 失敗を数えて、続けて間違えるとしばらくロックする。
 */
function account_login(string $email, string $password): array
{
  rate_limit_hit('login:ip:' . rate_limit_ip(), 20, 600);

  $user = db_one(
    'SELECT * FROM users WHERE email_normalized = :e AND deleted_at IS NULL',
    ['e' => strtolower(trim($email))]
  );

  // ユーザーの有無で応答時間が変わらないよう、見つからなくてもハッシュ比較は行う
  $hash = (string) ($user['password_hash'] ?? '$2y$12$invalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidinv');
  $ok   = password_verify($password, $hash);

  if ($user !== null && $user['locked_until'] !== null && strtotime((string) $user['locked_until']) > time()) {
    throw new AppError('アカウントが一時的にロックされています。しばらくしてからお試しください。');
  }

  if (!$ok || $user === null) {
    if ($user !== null) {
      $failed = (int) $user['failed_login_count'] + 1;
      $max    = (int) config('auth.max_login_failures', 5);
      $lock   = $failed >= $max
        ? date('Y-m-d H:i:s', time() + 60 * (int) config('auth.lock_minutes', 15))
        : null;
      db_update('users', ['failed_login_count' => $failed, 'locked_until' => $lock], 'id = :id', ['id' => $user['id']]);
    }
    throw new AppError('メールアドレスまたはパスワードが正しくありません。');
  }

  if ($user['status'] === 'suspended') {
    throw new AppError('このアカウントは利用停止中です。');
  }

  db_update(
    'users',
    ['failed_login_count' => 0, 'locked_until' => null, 'last_login_at' => date('Y-m-d H:i:s')],
    'id = :id',
    ['id' => $user['id']]
  );

  return $user;
}

// ---------------------------------------------------------------------
// SMS 認証（仕組みは残してあるが、現時点ではレベルの条件にしていない）
// ---------------------------------------------------------------------

function account_send_sms_code(int $userId, ?string $phone = null): void
{
  rate_limit_hit("sms:user:{$userId}", 5, 3600);

  $user   = db_one('SELECT phone_e164 FROM users WHERE id = :id', ['id' => $userId]);
  $phone ??= $user['phone_e164'] ?? null;
  if ($phone === null) {
    throw new AppError('電話番号が未登録です。');
  }

  $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
  $ttl  = (int) config('auth.sms_code_ttl', 600);

  db_run('UPDATE phone_verifications SET consumed_at = NOW() WHERE user_id = :u AND consumed_at IS NULL', ['u' => $userId]);
  db_insert('phone_verifications', [
    'user_id'    => $userId,
    'phone_e164' => $phone,
    'code_hash'  => hash('sha256', $code),
    'expires_at' => date('Y-m-d H:i:s', time() + $ttl),
  ]);

  notify_sms($phone, "【SPOTIVE】認証コード: {$code}（" . (int) ($ttl / 60) . '分間有効）');
}

function account_verify_sms(int $userId, string $code): void
{
  $row = db_one(
    'SELECT * FROM phone_verifications
      WHERE user_id = :u AND consumed_at IS NULL AND expires_at > NOW()
      ORDER BY id DESC LIMIT 1',
    ['u' => $userId]
  );
  if ($row === null) {
    throw new AppError('認証コードの有効期限が切れています。再送してください。');
  }
  if ((int) $row['attempt_count'] >= (int) $row['max_attempts']) {
    throw new AppError('試行回数の上限に達しました。コードを再送してください。');
  }

  db_run('UPDATE phone_verifications SET attempt_count = attempt_count + 1 WHERE id = :id', ['id' => $row['id']]);

  if (!hash_equals((string) $row['code_hash'], hash('sha256', trim($code)))) {
    throw new AppError('認証コードが正しくありません。', ['code' => '認証コードが正しくありません。']);
  }

  db_transaction(static function () use ($row, $userId): void {
    db_run('UPDATE phone_verifications SET consumed_at = NOW() WHERE id = :id', ['id' => $row['id']]);
    db_run(
      'UPDATE users SET phone_verified_at = NOW() WHERE id = :u AND phone_verified_at IS NULL',
      ['u' => $userId]
    );
    recalc_trust_level($userId);
    audit_log($userId, 'user.phone_verified', 'user', $userId);
  });
}

// ---------------------------------------------------------------------
// 信頼レベル
// ---------------------------------------------------------------------

/**
 * 信頼レベルの再計算。各レベルの根拠テーブルだけを見る唯一の場所。
 * ここを通さず users.trust_level を直接書き換えないこと。
 */
function recalc_trust_level(int $userId): int
{
  $u = db_one(
    'SELECT email_verified_at, phone_verified_at, trust_level FROM users WHERE id = :id',
    ['id' => $userId]
  );
  if ($u === null) {
    throw new AppError('ユーザーが見つかりません。');
  }

  $level = LEVEL_PROVISIONAL;

  // Lv.1: メール確認のみ。
  // SMS 認証は仕組みとしては残してあるが、現時点ではレベルの条件にしない
  // （実運用に耐える SMS 送信の準備ができたら phone_verified_at の条件を戻す）。
  if ($u['email_verified_at'] !== null) {
    $level = LEVEL_USER;
  }

  // Lv.2: 承認済みかつ有効期限内の本人確認がある
  if ($level >= LEVEL_USER) {
    $identity = db_one(
      'SELECT id FROM identity_verifications
        WHERE user_id = :u AND status = "approved"
          AND (expires_at IS NULL OR expires_at > NOW())
        LIMIT 1',
      ['u' => $userId]
    );
    if ($identity !== null) {
      $level = LEVEL_IDENTIFIED;
    }
  }

  // Lv.3: 有効な主催者資格がある
  if ($level >= LEVEL_IDENTIFIED) {
    $profile = db_one(
      'SELECT id FROM organizer_profiles
        WHERE user_id = :u AND status = "active"
          AND (verified_until IS NULL OR verified_until > NOW())
        LIMIT 1',
      ['u' => $userId]
    );
    if ($profile !== null) {
      $level = LEVEL_ORGANIZER;
    }
  }

  if ((int) $u['trust_level'] !== $level) {
    db_update('users', ['trust_level' => $level], 'id = :id', ['id' => $userId]);
    audit_log(null, 'user.trust_level_changed', 'user', $userId, [
      'from' => (int) $u['trust_level'],
      'to'   => $level,
    ]);
  }
  return $level;
}

/**
 * マイページ用のまとめ。「次に何をすればレベルが上がるか」も返す。
 */
function account_overview(int $userId): array
{
  $u = db_one('SELECT * FROM users WHERE id = :id', ['id' => $userId]);
  if ($u === null) {
    throw new AppError('ユーザーが見つかりません。');
  }

  $identity = db_one(
    'SELECT id, status, reject_reason, submitted_at, expires_at FROM identity_verifications
      WHERE user_id = :u ORDER BY id DESC LIMIT 1',
    ['u' => $userId]
  );
  $application = db_one(
    'SELECT id, applicant_type, status, review_note, submitted_at FROM organizer_applications
      WHERE user_id = :u ORDER BY id DESC LIMIT 1',
    ['u' => $userId]
  );
  $profile = db_one(
    'SELECT display_name, verified_at, verified_until, status FROM organizer_profiles WHERE user_id = :u',
    ['u' => $userId]
  );

  $level = (int) $u['trust_level'];
  $next  = match (true) {
    $u['email_verified_at'] === null => 'verify_email',
    $level < LEVEL_IDENTIFIED        => 'identity',
    $level < LEVEL_ORGANIZER         => 'organizer',
    default                          => null,
  };

  return [
    'user'        => $u,
    'level'       => $level,
    'level_label' => level_label($level),
    'identity'    => $identity,
    'application' => $application,
    'profile'     => $profile,
    'next_step'   => $next,
  ];
}

/** メールに載せる絶対 URL。config/app.php の base_url を土台にする */
function app_url(string $path): string
{
  return rtrim((string) config('app.base_url', ''), '/') . '/' . ltrim($path, '/');
}
