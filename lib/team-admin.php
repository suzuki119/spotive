<?php

/**
 * lib/team-admin.php
 * 主催者ページ（pages/administrators/administrators.php）に出すデータ。
 *
 * だれがどのチームを管理するかは team_admins に入っている（行を足すのは運営だけ。tools/add-team-admin.php）。
 * チーム・リーグ・試合はまだ DB に無いので、data/teams.json と data/matches.json から数える。
 * 大会（tournaments）は、チームではなく「自分が登録したもの」を出す。
 */

declare(strict_types=1);

require_once __DIR__ . '/support.php';
require_once __DIR__ . '/match.php';
require_once __DIR__ . '/access.php';

/**
 * 自分が管理するチーム（登録した順）
 *
 * @return list<array{team_id:string,display_name:string,role:string,team_name:string}>
 */
function team_admin_teams(int $userId): array
{
  $teams = load_teams();
  $rows  = db_all(
    'SELECT team_id, display_name, role FROM team_admins WHERE user_id = :u ORDER BY created_at ASC, team_id ASC',
    ['u' => $userId]
  );

  $list = [];
  foreach ($rows as $row) {
    $teamId = (string) $row['team_id'];
    $list[] = [
      'team_id'      => $teamId,
      'display_name' => (string) $row['display_name'],
      'role'         => (string) $row['role'],
      'team_name'    => (string) ($teams[$teamId]['name'] ?? $teamId),
    ];
  }
  return $list;
}

/**
 * チームの試合を、リーグ（リーグ名＋シーズン）ごとにまとめる
 *
 * @return list<array{title:string,first:string,last:string,upcoming:int}>
 */
function team_admin_leagues(string $teamId, string $today): array
{
  $leagues = [];
  foreach (load_data_json('matches.json') as $m) {
    if ($teamId === '' || !in_array($teamId, [(string) ($m['homeTeamId'] ?? ''), (string) ($m['awayTeamId'] ?? '')], true)) {
      continue;
    }
    $date = (string) ($m['date'] ?? '');
    if (preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $date) !== 1) {
      continue;
    }

    $title = trim((string) ($m['league'] ?? '') . ' ' . (string) ($m['season'] ?? ''));
    $league = $leagues[$title] ?? ['title' => $title, 'first' => $date, 'last' => $date, 'upcoming' => 0];
    $league['first'] = min($league['first'], $date);
    $league['last']  = max($league['last'], $date);
    if ($date >= $today) {
      $league['upcoming']++;
    }
    $leagues[$title] = $league;
  }

  usort($leagues, static fn(array $a, array $b): int => strcmp($a['first'], $b['first']));
  return $leagues;
}

/**
 * 管理者画面の中身をまとめて作る
 *
 * @param array{team_id:string,display_name:string,role:string,team_name:string}|null $team 選んでいるチーム（無ければ null）
 * @return array{
 *   access: array{page_view:int,ticket_click:int,favorite:int},
 *   events: array{leagues:int,upcoming_matches:int,upcoming_tournaments:int,drafts:int},
 *   published: list<array{title:string,term:string,place_label:string,place:string,tournament_id:?int,verification_status:string}>,
 *   unpublished: list<array{title:string,term:string,place_label:string,place:string,tournament_id:?int,verification_status:string,status:string}>
 * }
 */
function team_admin_dashboard(int $userId, ?array $team, DateTimeImmutable $now): array
{
  $today  = $now->format('Y-m-d');
  $teamId = $team['team_id'] ?? '';

  // 自分が登録した大会。主催者本人の管理画面なので tournaments を直接見る（AGENTS.md）
  $tournaments = db_all(
    'SELECT id, title, starts_at, ends_at, venue_name, status, verification_status
       FROM tournaments
      WHERE organizer_user_id = :u
      ORDER BY starts_at ASC',
    ['u' => $userId]
  );
  $published = array_values(array_filter($tournaments, static fn(array $t): bool => $t['status'] === 'published'));

  $leagues = team_admin_leagues($teamId, $today);

  $favorite = $teamId === '' ? 0 : (int) (db_one(
    'SELECT COUNT(*) AS n FROM favorite_teams WHERE team_id = :t',
    ['t' => $teamId]
  )['n'] ?? 0);

  $access = access_totals(
    $teamId === '' ? [] : [$teamId],
    array_map(static fn(array $t): int => (int) $t['id'], $published)
  );

  $list = [];
  foreach ($leagues as $league) {
    $list[] = [
      'title'       => $league['title'],
      'term'        => team_admin_term($league['first'], $league['last']),
      'place_label' => 'ホームアリーナ登録',
      'place'       => (string) (load_teams()[$teamId]['homeVenue'] ?? ''),
      // リーグは仮データなので、ここからは編集できない
      'tournament_id'       => null,
      'verification_status' => '',
    ];
  }
  foreach ($published as $t) {
    $list[] = team_admin_tournament_item($t);
  }

  // 下書き・中止・終了の大会。公開していないので「掲載中」とは分けて出す
  $unpublished = [];
  foreach ($tournaments as $t) {
    if ($t['status'] !== 'published') {
      $unpublished[] = team_admin_tournament_item($t) + ['status' => (string) $t['status']];
    }
  }

  return [
    'access' => $access + ['favorite' => $favorite],
    'events' => [
      'leagues'              => count($leagues),
      'upcoming_matches'     => array_sum(array_column($leagues, 'upcoming')),
      'upcoming_tournaments' => count(array_filter($published, static fn(array $t): bool => (string) $t['ends_at'] >= $now->format('Y-m-d H:i:s'))),
      'drafts'               => count(array_filter($tournaments, static fn(array $t): bool => $t['status'] === 'draft')),
    ],
    'published'   => $list,
    'unpublished' => $unpublished,
  ];
}

/** 一覧に出す大会 1 件 */
function team_admin_tournament_item(array $t): array
{
  return [
    'title'               => (string) $t['title'],
    'term'                => team_admin_term(substr((string) $t['starts_at'], 0, 10), substr((string) $t['ends_at'], 0, 10)),
    'place_label'         => '会場',
    'place'               => (string) $t['venue_name'],
    'tournament_id'       => (int) $t['id'],
    'verification_status' => (string) $t['verification_status'],
  ];
}

/** 「2026/09/27〜2027/05/06」。1 日だけなら日付 1 つ */
function team_admin_term(string $first, string $last): string
{
  $fmt = static fn(string $d): string => str_replace('-', '/', $d);
  return $first === $last ? $fmt($first) : $fmt($first) . '〜' . $fmt($last);
}
