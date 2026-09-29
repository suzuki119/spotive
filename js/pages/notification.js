/**
 * notification.js
 * お知らせ画面（pages/notification/notification.php）の処理。
 *   ・未ログインのときは、チーム向けのお知らせをお気に入りのチームの分だけ出す
 *     （お気に入りはブラウザに保存されているので、サーバーでは絞れない）
 *   ・カードを開いたら既読にする
 *       ログイン中 … notification-api.php に送って DB に保存
 *       未ログイン … ブラウザ（localStorage）に保存
 */

document.addEventListener("DOMContentLoaded", () => {
  const items = document.querySelectorAll("[data-notice]");
  if (items.length === 0) return;

  const READ_KEY = "spotive:read-notices";
  const READ_LIMIT = 200; // ブラウザに残す既読の件数（古いものから捨てる）

  /** ページが埋め込んだ状態。無ければ未ログイン扱い */
  let state = {};
  try {
    state = JSON.parse(document.getElementById("notice-state").textContent) || {};
  } catch (e) {
    state = {};
  }

  // プライベートブラウズなどで localStorage が使えないこともあるので、必ず try で囲む
  function readLocal() {
    try {
      const list = JSON.parse(localStorage.getItem(READ_KEY) || "[]");
      return Array.isArray(list) ? list.map(Number).filter(Number.isFinite) : [];
    } catch (e) {
      return [];
    }
  }

  function writeLocal(list) {
    try {
      localStorage.setItem(READ_KEY, JSON.stringify(list.slice(-READ_LIMIT)));
    } catch (e) {
      // 保存できなくても、この画面では既読の見た目になる
    }
  }

  function setUnread(item, unread) {
    item.querySelector(".notice-card").classList.toggle("is-unread", unread);
    item.querySelector("[data-notice-status]").textContent = unread ? "未読" : "";
  }

  // -----------------------------------------------------------------
  // 未ログイン：出すお知らせと既読を、ブラウザの内容で決める
  // -----------------------------------------------------------------
  if (!state.loggedIn) {
    const favorites = typeof SpotiveFavorites === "undefined" ? [] : SpotiveFavorites.teamIds();
    const readIds = readLocal();

    items.forEach((item) => {
      const teamId = item.dataset.teamId;
      if (teamId) item.hidden = !favorites.includes(teamId);
      setUnread(item, !readIds.includes(Number(item.dataset.noticeId)));
    });

    const empty = document.querySelector("[data-notice-empty]");
    if (empty) empty.hidden = [...items].some((item) => !item.hidden);
  }

  // -----------------------------------------------------------------
  // 開いたら既読にする
  // -----------------------------------------------------------------
  function markRead(item) {
    const card = item.querySelector(".notice-card");
    if (!card.classList.contains("is-unread")) return;
    setUnread(item, false);

    const id = Number(item.dataset.noticeId);
    if (!state.loggedIn) {
      writeLocal([...new Set([...readLocal(), id])]);
      return;
    }

    const body = new FormData();
    body.append("csrf_token", state.csrf || "");
    body.append("action", "read");
    body.append("ids[]", String(id));
    fetch(state.endpoint, { method: "POST", body, credentials: "same-origin" })
      .then((res) => res.json().then((json) => {
        if (!res.ok || !json.ok) throw new Error(json.message || "保存できませんでした。");
      }))
      .catch((e) => {
        // 保存できなかったら未読に戻す（次に開いたときにもう一度送る）
        setUnread(item, true);
        console.error("[SPOTIVE] 既読を保存できませんでした", e);
      });
  }

  items.forEach((item) => {
    item.querySelector(".notice-card").addEventListener("toggle", (e) => {
      if (e.target.open) markRead(item);
    });
  });
});
