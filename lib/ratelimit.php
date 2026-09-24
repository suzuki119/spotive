<?php

/**
 * lib/ratelimit.php
 * 固定ウィンドウの回数制限。メール送信・ログイン・申請の連打を防ぐ。
 */

declare(strict_types=1);

require_once __DIR__ . '/support.php';

/**
 * $bucket の回数を 1 つ増やす。上限を超えていれば AppError を投げる。
 * $bucket は 'login:ip:1.2.3.4' のように「用途:対象」で作る。
 */
function rate_limit_hit(string $bucket, int $limit, int $windowSeconds): void
{
  $now = time();

  db_transaction(static function () use ($bucket, $limit, $windowSeconds, $now): void {
    // 同時アクセスで数え落とさないよう、行を押さえてから読む
    $row = db_one('SELECT window_start, counter FROM rate_limits WHERE bucket = :b FOR UPDATE', ['b' => $bucket]);

    if ($row === null) {
      db_run(
        'INSERT INTO rate_limits (bucket, window_start, counter) VALUES (:b, :w, 1)',
        ['b' => $bucket, 'w' => date('Y-m-d H:i:s', $now)]
      );
      return;
    }

    $windowEnd = strtotime((string) $row['window_start']) + $windowSeconds;
    if ($windowEnd < $now) {
      // 前の期間は終わっているので数え直す
      db_run(
        'UPDATE rate_limits SET window_start = :w, counter = 1 WHERE bucket = :b',
        ['b' => $bucket, 'w' => date('Y-m-d H:i:s', $now)]
      );
      return;
    }

    if ((int) $row['counter'] >= $limit) {
      $retry = $windowEnd - $now;
      throw new AppError("操作の回数が多すぎます。約{$retry}秒後にもう一度お試しください。");
    }

    db_run('UPDATE rate_limits SET counter = counter + 1 WHERE bucket = :b', ['b' => $bucket]);
  });
}

/** 制限の単位に使うリクエスト元 IP */
function rate_limit_ip(): string
{
  return (string) ($_SERVER['REMOTE_ADDR'] ?? '-');
}
