<?php

/**
 * lib/notification-admin.php
 * 運営がお知らせを作る・直す・取り消すための処理（pages/admin/notification.php から使う）。
 *
 * 見る側（lib/notification.php）は公開用ビュー v_public_notifications から取るが、
 * ここは下書きや予約中も扱うので notifications を直接見る（運営の管理画面だけの例外）。
 * 作成・更新・取り消し・削除は audit_logs に残す。
 */

declare(strict_types=1);

require_once __DIR__ . '/notification.php';
require_once __DIR__ . '/validator.php';
require_once __DIR__ . '/audit.php';

/** 届け先 => 管理画面に出す名前 */
const NOTIFICATION_AUDIENCES = [
  'all'  => '全員',
  'team' => 'チームのファン（お気に入り登録者）',
];

/** 種類 => 管理画面に出す名前 */
const NOTIFICATION_CATEGORIES = [
  'info'   => 'お知らせ',
  'change' => '日時・会場の変更',
  'cancel' => '中止',
];

/** 運営からのお知らせの、送り主の既定の表記 */
const NOTIFICATION_DEFAULT_SENDER = 'SPOTIVE 運営';

/** 本文の文字数の上限 */
const NOTIFICATION_BODY_MAX = 2000;

/**
 * 一覧。新しいものから。状態は「公開中／予約中／掲載終了」まで分けて返す。
 *
 * @return list<array<string,mixed>>
 */
function notification_admin_list(): array
{
  $rows = db_all(
    'SELECT n.id, n.audience, n.team_id, n.category, n.sender_name, n.title,
            n.status, n.published_at, n.expires_at, n.updated_at,
            (SELECT COUNT(*) FROM notification_reads r WHERE r.notification_id = n.id) AS read_count
       FROM notifications n
      ORDER BY COALESCE(n.published_at, n.created_at) DESC, n.id DESC
      LIMIT 200'
  );

  return array_map(static fn(array $n): array => $n + ['state' => notification_admin_state($n)], $rows);
}

/** 1 件。無ければ null */
function notification_admin_find(int $id): ?array
{
  return db_one('SELECT * FROM notifications WHERE id = :id', ['id' => $id]);
}

/**
 * 画面に出す状態。status だけでは「公開済みだが予約中・掲載終了」が分からないので、日時も見る。
 *
 * @return 'draft'|'scheduled'|'published'|'expired'|'withdrawn'
 */
function notification_admin_state(array $n): string
{
  if ($n['status'] === 'draft' || $n['status'] === 'withdrawn') {
    return (string) $n['status'];
  }
  $now = time();
  if ($n['published_at'] === null || strtotime((string) $n['published_at']) > $now) {
    return 'scheduled';
  }
  if ($n['expires_at'] !== null && strtotime((string) $n['expires_at']) <= $now) {
    return 'expired';
  }
  return 'published';
}

/** 状態 => 表示名 */
const NOTIFICATION_STATE_LABELS = [
  'draft'     => '下書き',
  'scheduled' => '予約中',
  'published' => '公開中',
  'expired'   => '掲載終了',
  'withdrawn' => '取り消し',
];

/**
 * 関係する試合の選択肢（data/matches.json の、これからの試合）。
 *
 * @return array<string,string> match-001 => 「10/3 横浜 vs 東京（みなとみらいスタジアム）」
 */
function notification_admin_match_options(): array
{
  $options = [];
  foreach (load_upcoming_matches() as $m) {
    if (!str_starts_with((string) $m['key'], 'json-')) {
      continue;   // 大会は tournament_id で持つので、ここでは仮データの試合だけ
    }
    $id = substr((string) $m['key'], strlen('json-'));
    $options[$id] = match_short_date((string) $m['date']) . ' ' . $m['title'] . '（' . $m['venue'] . '）';
  }
  return $options;
}

/**
 * 作成・更新。$id が null なら新しく作る。
 * 公開（publish = 1）で公開日時が空なら、今の時刻で公開する。
 *
 * @return int お知らせの ID
 */
function notification_admin_save(array $admin, ?int $id, array $in): int
{
  $teams   = load_teams();
  $matches = notification_admin_match_options();

  $data = [
    'audience'     => (string) ($in['audience'] ?? ''),
    'team_id'      => trim((string) ($in['team_id'] ?? '')),
    'category'     => (string) ($in['category'] ?? ''),
    'sender_name'  => trim((string) ($in['sender_name'] ?? '')),
    'title'        => trim((string) ($in['title'] ?? '')),
    'body'         => trim((string) ($in['body'] ?? '')),
    'match_ref'    => trim((string) ($in['match_ref'] ?? '')),
    'published_at' => trim((string) ($in['published_at'] ?? '')),
    'expires_at'   => trim((string) ($in['expires_at'] ?? '')),
  ];
  $publish = ($in['publish'] ?? '') === '1';

  $v = (new Validator($data))
    ->required('audience', '届け先')->in('audience', '届け先', array_keys(NOTIFICATION_AUDIENCES))
    ->required('category', '種類')->in('category', '種類', array_keys(NOTIFICATION_CATEGORIES))
    ->required('sender_name', '送り主')->length('sender_name', '送り主', 1, 120)
    ->required('title', 'タイトル')->length('title', 'タイトル', 1, 150)
    ->length('body', '本文', 1, NOTIFICATION_BODY_MAX)
    ->datetime('published_at', '公開日時')
    ->datetime('expires_at', '掲載終了日時');

  if ($data['audience'] === 'team' && !isset($teams[$data['team_id']])) {
    $v->add('team_id', 'チームを選んでください。');
  }
  // 選択肢に無い試合（終わった試合など）は、編集前から付いていたものだけ残せる
  $current = $id === null ? null : notification_admin_find($id);
  if ($data['match_ref'] !== '' && !isset($matches[$data['match_ref']])
      && ($current === null || $current['match_ref'] !== $data['match_ref'])) {
    $v->add('match_ref', '関係する試合を選び直してください。');
  }
  if ($data['published_at'] !== '' && $data['expires_at'] !== ''
      && strtotime($data['expires_at']) <= strtotime($data['published_at'])) {
    $v->add('expires_at', '掲載終了日時は、公開日時より後にしてください。');
  }
  $v->validate();

  if ($id !== null && $current === null) {
    throw new AppError('お知らせが見つかりません。');
  }

  $toDb = static fn(string $v): ?string => $v === '' ? null : date('Y-m-d H:i:s', (int) strtotime($v));

  $row = [
    'audience'     => $data['audience'],
    'team_id'      => $data['audience'] === 'team' ? $data['team_id'] : null,
    'category'     => $data['category'],
    'sender_name'  => $data['sender_name'],
    'title'        => $data['title'],
    'body'         => $data['body'] === '' ? null : $data['body'],
    'match_ref'    => $data['match_ref'] === '' ? null : $data['match_ref'],
    'published_at' => $toDb($data['published_at']),
    'expires_at'   => $toDb($data['expires_at']),
  ];

  if ($publish) {
    $row['status'] = 'published';
    $row['published_at'] ??= date('Y-m-d H:i:s');
  } elseif ($current === null) {
    $row['status'] = 'draft';
  }
  // 公開済みを「下書きで保存」したときは、公開の状態はそのまま（本文の修正だけ）

  if ($row['expires_at'] !== null && $row['published_at'] !== null && $row['expires_at'] <= $row['published_at']) {
    throw new AppError('入力内容を確認してください。', ['expires_at' => '掲載終了日時は、公開日時より後にしてください。']);
  }

  if ($current === null) {
    $row['created_by'] = (int) $admin['id'];
    $id = db_insert('notifications', $row);
    audit_log((int) $admin['id'], 'notification.create', 'notification', $id, ['publish' => $publish]);
  } else {
    db_update('notifications', $row, 'id = :id', ['id' => $id]);
    audit_log((int) $admin['id'], 'notification.update', 'notification', $id, ['publish' => $publish]);
  }
  return $id;
}

/** 公開を取り消す（見る側から消える。既読の記録は残す） */
function notification_admin_withdraw(array $admin, int $id): void
{
  if (notification_admin_find($id) === null) {
    throw new AppError('お知らせが見つかりません。');
  }
  db_update('notifications', ['status' => 'withdrawn'], 'id = :id', ['id' => $id]);
  audit_log((int) $admin['id'], 'notification.withdraw', 'notification', $id);
}

/** 削除は下書きだけ（一度公開したものは、取り消しにして記録を残す） */
function notification_admin_delete(array $admin, int $id): void
{
  $n = notification_admin_find($id);
  if ($n === null) {
    throw new AppError('お知らせが見つかりません。');
  }
  if ($n['status'] !== 'draft') {
    throw new AppError('削除できるのは下書きだけです。公開したお知らせは「取り消す」を使ってください。');
  }
  db_run('DELETE FROM notifications WHERE id = :id', ['id' => $id]);
  audit_log((int) $admin['id'], 'notification.delete', 'notification', $id);
}
