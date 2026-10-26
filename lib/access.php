<?php

/**
 * lib/access.php
 * アクセス数（管理者画面のアクセスボード）。access_daily_counts に 1 日ごとの合計だけを持つ。
 * 誰が見たかは残さない（ユーザー ID・IP は保存しない）。
 *
 *   記録 … 試合詳細を開いた（page_view）、チケット販売サイトへのリンクを押した（ticket_click）。
 *          js/pages/match-detail.js が pages/match/access-api.php に送る
 *   集計 … access_totals() で、チーム・大会ごとの合計を出す
 *
 * 試合はまだ DB に無いので、試合の閲覧はホームとアウェイの両チームに 1 ずつ数える。
 */

declare(strict_types=1);

require_once __DIR__ . '/support.php';
require_once __DIR__ . '/match.php';

const ACCESS_METRICS = ['page_view', 'ticket_click'];

/**
 * 試合・大会を、数える対象（チーム・大会）に直す。存在しないものは空配列
 *
 * @return list<array{0:string,1:string}> [target_type, target_id] の一覧
 */
function access_targets(string $type, string $id): array
{
  if ($type === 'match' && preg_match('/\Amatch-\d+\z/', $id) === 1) {
    foreach (load_data_json('matches.json') as $m) {
      if ((string) ($m['id'] ?? '') !== $id) {
        continue;
      }
      $teamIds = array_unique(array_filter([(string) ($m['homeTeamId'] ?? ''), (string) ($m['awayTeamId'] ?? '')]));
      return array_map(static fn(string $teamId): array => ['team', $teamId], array_values($teamIds));
    }
    return [];
  }

  if ($type === 'tournament' && preg_match('/\A[1-9]\d{0,18}\z/', $id) === 1) {
    $row = db_one('SELECT id FROM v_public_tournaments WHERE id = :id', ['id' => (int) $id]);
    return $row === null ? [] : [['tournament', (string) $row['id']]];
  }

  return [];
}

/** 今日の分に 1 を足す */
function access_count(string $type, string $id, string $metric): void
{
  if (!in_array($metric, ACCESS_METRICS, true)) {
    throw new AppError('操作が不正です。');
  }
  foreach (access_targets($type, $id) as [$targetType, $targetId]) {
    db_run(
      'INSERT INTO access_daily_counts (stat_date, target_type, target_id, metric, count)
       VALUES (CURRENT_DATE, :type, :id, :metric, 1)
       ON DUPLICATE KEY UPDATE count = count + 1',
      ['type' => $targetType, 'id' => $targetId, 'metric' => $metric]
    );
  }
}

/**
 * チーム・大会の合計（期間を区切らない累計）
 *
 * @param string[] $teamIds       data/teams.json の id
 * @param int[]    $tournamentIds tournaments.id
 * @return array{page_view:int,ticket_click:int}
 */
function access_totals(array $teamIds, array $tournamentIds): array
{
  $totals  = ['page_view' => 0, 'ticket_click' => 0];
  $targets = [];
  foreach ($teamIds as $teamId) {
    $targets[] = ['team', (string) $teamId];
  }
  foreach ($tournamentIds as $tournamentId) {
    $targets[] = ['tournament', (string) (int) $tournamentId];
  }
  if ($targets === []) {
    return $totals;
  }

  // (target_type, target_id) の組をプレースホルダで並べる
  $where  = [];
  $params = [];
  foreach ($targets as $i => [$type, $id]) {
    $where[]          = "(target_type = :t{$i} AND target_id = :i{$i})";
    $params["t{$i}"] = $type;
    $params["i{$i}"] = $id;
  }
  $rows = db_all(
    'SELECT metric, SUM(count) AS total FROM access_daily_counts
      WHERE ' . implode(' OR ', $where) . '
      GROUP BY metric',
    $params
  );
  foreach ($rows as $row) {
    $totals[(string) $row['metric']] = (int) $row['total'];
  }
  return $totals;
}
