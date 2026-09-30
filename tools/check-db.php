<?php

/**
 * tools/check-db.php
 * 【一時的な確認用】サーバーの DB が、会員登録に必要な状態になっているかを調べる。
 * ブラウザで tools/check-db.php を開くと、結果が一覧で出る。
 *
 * 出すのは「つながるか」「テーブルがあるか」「規約のデータがあるか」だけで、
 * パスワード・接続先・エラーの本文などは出さない（エラーは番号だけ）。
 * それでも公開したままにしないこと。確認が終わったらサーバーから削除する。
 * 念のため、CHECK_DB_UNTIL を過ぎると開けなくなる。
 */

declare(strict_types=1);

const CHECK_DB_UNTIL = '2026-10-02 23:59:59';

if (PHP_SAPI !== 'cli' && time() > strtotime(CHECK_DB_UNTIL)) {
  http_response_code(404);
  exit;
}

require_once __DIR__ . '/../config/db.php';

header('Content-Type: text/plain; charset=UTF-8');
header('X-Robots-Tag: noindex');

/** 会員登録（lib/account.php の account_register）が使うテーブル */
const CHECK_DB_TABLES = ['users', 'policy_documents', 'user_agreements', 'audit_logs', 'rate_limits', 'email_verifications'];

$lines = [];
$ok    = true;

try {
  $pdo = db();
  $lines[] = '[OK] DB につながりました（MySQL ' . $pdo->query('SELECT VERSION()')->fetchColumn() . '）';
} catch (PDOException $e) {
  // 本文には接続先やユーザー名が入ることがあるので、番号だけ出す
  $code = $e->errorInfo[1] ?? $e->getCode();
  echo "[NG] DB につながりません（エラー番号 {$code}）\n";
  echo "     → config/db.local.php の接続先・DB 名・ユーザー・パスワードを確認してください。\n";
  echo "       1045 = ユーザーかパスワードが違う / 1049 = DB 名が無い / 2002・2005 = 接続先（host）が違う\n";
  exit;
}

foreach (CHECK_DB_TABLES as $table) {
  $exists = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :t');
  $exists->execute([':t' => $table]);
  if ((int) $exists->fetchColumn() === 1) {
    $lines[] = "[OK] テーブル {$table} があります";
  } else {
    $lines[] = "[NG] テーブル {$table} がありません → db/schema-lolipop.sql を流し込んでください";
    $ok = false;
  }
}

// 規約（利用規約・プライバシーポリシー）が入っていないと登録できない
try {
  $kinds = $pdo->query(
    "SELECT kind, COUNT(*) FROM policy_documents
      WHERE retired_at IS NULL AND published_at <= NOW()
      GROUP BY kind"
  )->fetchAll(PDO::FETCH_KEY_PAIR);
  foreach (['terms' => '利用規約', 'privacy' => 'プライバシーポリシー'] as $kind => $label) {
    if (($kinds[$kind] ?? 0) > 0) {
      $lines[] = "[OK] {$label}のデータがあります";
    } else {
      $lines[] = "[NG] {$label}のデータがありません → db/seed.sql を流し込んでください";
      $ok = false;
    }
  }
} catch (PDOException $e) {
  $lines[] = '[NG] 規約のデータを読めません（エラー番号 ' . ($e->errorInfo[1] ?? $e->getCode()) . '）';
  $ok = false;
}

// 登録で使う列がそろっているか（古いスキーマのままだと足りないことがある）
try {
  $cols = $pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN);
  $need = ['email', 'email_normalized', 'phone_e164', 'password_hash', 'nickname', 'birthdate', 'trust_level', 'status'];
  $missing = array_diff($need, $cols);
  $lines[] = $missing === []
    ? '[OK] users の列がそろっています'
    : '[NG] users に列がありません：' . implode(', ', $missing) . ' → db/schema-lolipop.sql で作り直してください';
  $ok = $ok && $missing === [];
} catch (PDOException $e) {
  // テーブルが無いときは上で NG になっている
}

// 書き込みの確認（取り消すので、データは残らない）
try {
  $pdo->beginTransaction();
  $pdo->prepare('INSERT INTO rate_limits (bucket, window_start, counter) VALUES (:b, NOW(), 1)')
      ->execute([':b' => 'check-db:' . bin2hex(random_bytes(4))]);
  $pdo->rollBack();
  $lines[] = '[OK] 書き込みができます';
} catch (PDOException $e) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  $lines[] = '[NG] 書き込みができません（エラー番号 ' . ($e->errorInfo[1] ?? $e->getCode()) . '）';
  $ok = false;
}

echo implode("\n", $lines), "\n\n";
echo $ok
  ? "すべて OK です。DB の準備は整っています。\n"
  : "NG の項目を直してから、もう一度登録を試してください。\n";
echo "\n※ 確認が終わったら、このファイル（tools/check-db.php）をサーバーから削除してください。\n";
