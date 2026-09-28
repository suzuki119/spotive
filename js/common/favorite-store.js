/**
 * favorite-store.js
 * お気に入りのチームの保存先をまとめる。
 * 試合一覧（js/pages/match-list.js）とお気に入り（js/pages/favorite.js）から使う。
 *
 *   ログイン中 … DB（pages/favorite/favorite-api.php 経由。どの端末からでも同じ）
 *   未ログイン … ブラウザの localStorage（同じ端末・同じブラウザの中だけ）
 *
 * どちらになるかは、ページが埋め込む #favorite-state（lib/favorite.php）で決まる。
 * ログイン中にブラウザ側の登録が残っていれば、アカウントに移してからブラウザ側を消す。
 *
 * ページをまたいで使うため、SpotiveFavorites だけをグローバルに置く。
 */

const SpotiveFavorites = (() => {
  const KEY = "spotive:favorite-teams";

  /** ページが埋め込んだ初期状態。無ければ未ログイン扱い */
  function readState() {
    const el = document.getElementById("favorite-state");
    try {
      const state = JSON.parse(el ? el.textContent : "{}");
      return state && typeof state === "object" ? state : {};
    } catch (e) {
      return {};
    }
  }

  const state = readState();
  // ログイン中は DB の内容を手元に持っておく。未ログインなら null（localStorage を見る）
  let accountIds = state.loggedIn && Array.isArray(state.teamIds) ? state.teamIds : null;
  const listeners = [];

  // プライベートブラウズなどで localStorage が使えないこともあるので、必ず try で囲む
  function readLocal() {
    try {
      const list = JSON.parse(localStorage.getItem(KEY) || "[]");
      return Array.isArray(list) ? list.filter((id) => typeof id === "string") : [];
    } catch (e) {
      return [];
    }
  }

  function writeLocal(list) {
    try {
      localStorage.setItem(KEY, JSON.stringify(list));
      return true;
    } catch (e) {
      return false;
    }
  }

  function clearLocal() {
    try {
      localStorage.removeItem(KEY);
    } catch (e) {
      // 消せなくても、ログイン中は DB の内容を使うので困らない
    }
  }

  /** 登録内容が変わったことを、画面側に知らせる */
  function notify() {
    listeners.forEach((fn) => fn());
  }

  /** favorite-api.php に送り、保存後の一覧を受け取る */
  async function send(fields) {
    const body = new FormData();
    body.append("csrf_token", state.csrf || "");
    Object.entries(fields).forEach(([key, value]) => {
      if (Array.isArray(value)) {
        value.forEach((v) => body.append(`${key}[]`, v));
      } else {
        body.append(key, value);
      }
    });

    const res = await fetch(state.endpoint, { method: "POST", body, credentials: "same-origin" });
    const json = await res.json();
    if (!res.ok || !json.ok) {
      throw new Error(json.message || "保存できませんでした。");
    }
    return json.teamIds;
  }

  /** お気に入りのチーム ID の一覧 */
  function teamIds() {
    return accountIds ? [...accountIds] : readLocal();
  }

  function has(teamId) {
    return teamIds().includes(teamId);
  }

  /**
   * 登録・解除を切り替える。切り替えたあとの状態（登録中なら true）を返す。
   * ログイン中は先に画面へ反映し、保存に失敗したら元に戻して知らせる。
   */
  function toggle(teamId) {
    const list = teamIds();
    const on = !list.includes(teamId);
    const next = on ? [...list, teamId] : list.filter((id) => id !== teamId);

    if (!accountIds) {
      writeLocal(next);
      return on;
    }

    const before = accountIds;
    accountIds = next;
    send({ action: "toggle", team_id: teamId, on: on ? "1" : "0" })
      .then((ids) => {
        accountIds = ids;
        notify();
      })
      .catch((e) => {
        accountIds = before;
        notify();
        console.error("[SPOTIVE] お気に入りを保存できませんでした", e);
      });
    return on;
  }

  /** 試合カード（data-team-ids を持つ要素）が、お気に入りのチームの試合か */
  function isFavoriteMatch(card, favorites = teamIds()) {
    const ids = (card.dataset.teamIds || "").split(" ").filter(Boolean);
    return ids.some((id) => favorites.includes(id));
  }

  /** 登録内容が変わったとき（保存の失敗・アカウントへの移行）に呼ぶ関数を登録する */
  function onChange(fn) {
    listeners.push(fn);
  }

  // ログインする前にブラウザへ保存していた分を、アカウントに移す
  if (accountIds) {
    const local = readLocal();
    if (local.length > 0) {
      send({ action: "merge", team_ids: local })
        .then((ids) => {
          accountIds = ids;
          clearLocal();
          notify();
        })
        .catch((e) => console.error("[SPOTIVE] お気に入りをアカウントに移せませんでした", e));
    }
  }

  return { teamIds, has, toggle, isFavoriteMatch, onChange };
})();
