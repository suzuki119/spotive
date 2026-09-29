<?php

/**
 * lib/notification.php
 * お知らせ（運営からのお知らせ ＋ お気に入りチームのお知らせ）。
 *
 * 取るのは必ず公開用ビュー v_public_notifications から（下書き・予約中・掲載終了を出さないため）。
 *   ログイン中 … 全員向け ＋ お気に入りチーム（favorite_teams）向けだけを返し、既読（notification_reads）も付ける
 *   未ログイン … 全員向け ＋ すべてのチーム向けを返す。どのチームを出すかと既読は、
 *                ブラウザ側（js/pages/notification.js と localStorage）で決める
 */

declare(strict_types=1);

require_once __DIR__ . '/match.php';
require_once __DIR__ . '/auth.php';

/** 種類 => 画面に出すラベル。info は目印を出さない */
const NOTIFICATION_CATEGORY_LABELS = [
  'info'   => '',
  'change' => '変更',
  'cancel' => '中止',
];

/** 一覧に出す件数の上限 */
const NOTIFICATION_LIMIT = 50;

/** 一度に既読にできる件数の上限（ブラウザから大量に送られてきても受け付けすぎない） */
const NOTIFICATION_READ_LIMIT = 100;

/**
 * 画面に出すお知らせ。新しい順。
 *
 * @return list<array{
 *   id:int, audience:string, teamId:string, category:string, sender:string,
 *   title:string, body:string, publishedAt:string, detailPath:string, isRead:?bool
 * }> isRead は未ログインのとき null（ブラウザ側で決める）
 */
function notification_list(?int $userId): array
{
  if ($userId === null) {
    $rows = db_all(
      'SELECT id, audience, team_id, category, sender_name, title, body,
              match_ref, tournament_id, published_at, NULL AS is_read
         FROM v_public_notifications
        ORDER BY published_at DESC, id DESC
        LIMIT ' . NOTIFICATION_LIMIT
    );
  } else {
    // ネイティブのプレースホルダは同じ名前を 2 回使えないので、:u と :u2 に分ける
    $rows = db_all(
      'SELECT n.id, n.audience, n.team_id, n.category, n.sender_name, n.title, n.body,
              n.match_ref, n.tournament_id, n.published_at,
              (r.notification_id IS NOT NULL) AS is_read
         FROM v_public_notifications n
         LEFT JOIN notification_reads r ON r.notification_id = n.id AND r.user_id = :u
        WHERE n.audience = "all"
           OR n.team_id IN (SELECT team_id FROM favorite_teams WHERE user_id = :u2)
        ORDER BY n.published_at DESC, n.id DESC
        LIMIT ' . NOTIFICATION_LIMIT,
      ['u' => $userId, 'u2' => $userId]
    );
  }

  return array_map(static fn(array $n): array => [
    'id'          => (int) $n['id'],
    'audience'    => (string) $n['audience'],
    'teamId'      => (string) ($n['team_id'] ?? ''),
    'category'    => array_key_exists((string) $n['category'], NOTIFICATION_CATEGORY_LABELS) ? (string) $n['category'] : 'info',
    'sender'      => (string) $n['sender_name'],
    'title'       => (string) $n['title'],
    'body'        => (string) ($n['body'] ?? ''),
    'publishedAt' => (string) $n['published_at'],
    'detailPath'  => notification_detail_path($n),
    'isRead'      => $n['is_read'] === null ? null : (bool) $n['is_read'],
  ], $rows);
}

/** 関係する試合・大会の詳細ページ。無ければ空文字 */
function notification_detail_path(array $n): string
{
  if (($n['match_ref'] ?? null) !== null && $n['match_ref'] !== '') {
    return match_detail_path('match', (string) $n['match_ref']);
  }
  if (($n['tournament_id'] ?? null) !== null) {
    return match_detail_path('tournament', (string) (int) $n['tournament_id']);
  }
  return '';
}

/**
 * 既読にする。公開中のお知らせだけを対象にする（存在しない ID や下書きは黙って飛ばす）。
 *
 * @param array<mixed> $ids
 */
function notification_mark_read(int $userId, array $ids): void
{
  $ids = array_slice(array_values(array_unique(array_filter(
    array_map(static fn($id): int => (int) $id, $ids),
    static fn(int $id): bool => $id > 0
  ))), 0, NOTIFICATION_READ_LIMIT);

  foreach ($ids as $id) {
    db_run(
      'INSERT IGNORE INTO notification_reads (user_id, notification_id)
       SELECT :u, id FROM v_public_notifications WHERE id = :id',
      ['u' => $userId, 'id' => $id]
    );
  }
}

/** 2026-09-25 18:00:00 => 2026/09/25 18:00 */
function notification_date(string $datetime): string
{
  $time = strtotime($datetime);
  return $time === false ? '' : date('Y/m/d H:i', $time);
}
