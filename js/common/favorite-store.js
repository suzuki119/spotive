/**
 * favorite-store.js
 * お気に入りのチームを、ブラウザの localStorage に保存する。
 * 試合一覧（js/pages/match-list.js）とお気に入り（js/pages/favorite.js）から使う。
 *
 * DB に保存する形は、観戦用のテーブル（チーム・試合）と一緒にチームで設計する。
 * それまでの仮の保存先なので、同じ端末・同じブラウザの中だけで有効。
 *
 * ページをまたいで使うため、SpotiveFavorites だけをグローバルに置く。
 */

const SpotiveFavorites = (() => {
  const KEY = "spotive:favorite-teams";

  // プライベートブラウズなどで localStorage が使えないこともあるので、必ず try で囲む
  function read() {
    try {
      const list = JSON.parse(localStorage.getItem(KEY) || "[]");
      return Array.isArray(list) ? list.filter((id) => typeof id === "string") : [];
    } catch (e) {
      return [];
    }
  }

  function write(list) {
    try {
      localStorage.setItem(KEY, JSON.stringify(list));
      return true;
    } catch (e) {
      return false;
    }
  }

  /** お気に入りのチーム ID の一覧 */
  function teamIds() {
    return read();
  }

  function has(teamId) {
    return read().includes(teamId);
  }

  /** 登録・解除を切り替える。切り替えたあとの状態（登録中なら true）を返す */
  function toggle(teamId) {
    const list = read();
    const next = list.includes(teamId)
      ? list.filter((id) => id !== teamId)
      : [...list, teamId];
    write(next);
    return next.includes(teamId);
  }

  /** 試合カード（data-team-ids を持つ要素）が、お気に入りのチームの試合か */
  function isFavoriteMatch(card, favorites = read()) {
    const ids = (card.dataset.teamIds || "").split(" ").filter(Boolean);
    return ids.some((id) => favorites.includes(id));
  }

  return { teamIds, has, toggle, isFavoriteMatch };
})();
