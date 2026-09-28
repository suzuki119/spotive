<?php

/**
 * lib/favorite.php
 * お気に入りのチーム（企画書の機能 4）。
 *   ログイン中 … favorite_teams テーブルに保存する（どの端末から開いても同じ）
 *   未ログイン … ブラウザの localStorage に保存する（js/common/favorite-store.js）
 * ログインしたら、ブラウザに残っていた分を favorite_merge() でアカウントに移す。
 *
 * チームはまだ DB に無いので、team_id は data/teams.json の id（team-001 の形）をそのまま使う。
 */

declare(strict_types=1);

require_once __DIR__ . '/support.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/match.php';

/** 一度に移せる件数の上限（ブラウザから大量に送られてきても受け付けすぎない） */
const FAVORITE_MERGE_LIMIT = 200;

/** @return string[] お気に入りのチーム ID（登録した順） */
function favorite_team_ids(int $userId): array
{
  $rows = db_all(
    'SELECT team_id FROM favorite_teams WHERE user_id = :u ORDER BY created_at ASC, team_id ASC',
    ['u' => $userId]
  );
  return array_map(static fn(array $row): string => (string) $row['team_id'], $rows);
}

/** 登録（$on = true）または解除。存在しないチームは登録させない */
function favorite_set(int $userId, string $teamId, bool $on): void
{
  if (!$on) {
    db_run(
      'DELETE FROM favorite_teams WHERE user_id = :u AND team_id = :t',
      ['u' => $userId, 't' => $teamId]
    );
    return;
  }

  if (!isset(load_teams()[$teamId])) {
    throw new AppError('チームが見つかりません。');
  }
  favorite_insert($userId, $teamId);
}

/** ログイン前にブラウザへ保存していた分を、アカウントに移す。登録済み・存在しないチームは飛ばす */
function favorite_merge(int $userId, array $teamIds): void
{
  $known = load_teams();
  $ids   = array_values(array_unique(array_filter(
    $teamIds,
    static fn(mixed $id): bool => is_string($id) && isset($known[$id])
  )));
  $ids = array_slice($ids, 0, FAVORITE_MERGE_LIMIT);

  db_transaction(static function () use ($userId, $ids): void {
    foreach ($ids as $id) {
      favorite_insert($userId, $id);
    }
  });
}

/** 登録済みなら何もしない */
function favorite_insert(int $userId, string $teamId): void
{
  db_run(
    'INSERT INTO favorite_teams (user_id, team_id) VALUES (:u, :t)
       ON DUPLICATE KEY UPDATE team_id = team_id',
    ['u' => $userId, 't' => $teamId]
  );
}

/**
 * ページに埋め込み、js/common/favorite-store.js が最初に読む状態。
 * DB に繋がらないときや favorite_teams がまだ無いときは、未ログイン扱い（localStorage）にする。
 */
function favorite_client_state(): array
{
  $state = [
    'loggedIn' => false,
    'teamIds'  => [],
    'endpoint' => url('pages/favorite/favorite-api.php'),
    'csrf'     => '',
  ];

  try {
    $user = current_user();
    if ($user !== null) {
      $state['teamIds']  = favorite_team_ids((int) $user['id']);
      $state['loggedIn'] = true;
      $state['csrf']     = csrf_token();
    }
  } catch (PDOException $e) {
    error_log('[SPOTIVE] DB error: ' . $e->getMessage());
  }

  return $state;
}

/** <script type="application/json"> の中に置くので、タグやクォートは実体参照に逃がす */
function favorite_state_json(array $state): string
{
  return (string) json_encode(
    $state,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
      | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
  );
}
