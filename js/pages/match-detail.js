/**
 * match-detail.js
 * 試合詳細画面（pages/match/match-detail.php）の処理。
 *   ・右上の ✕ で、来た画面に戻る
 *   ・「現在地から」に会場までの距離を出す（位置情報がすでに許可されているときだけ）
 *   ・対戦チームの ☆ でお気に入りに登録・解除する（js/common/favorite-store.js）
 */

document.addEventListener("DOMContentLoaded", () => {
  // -----------------------------------------------------------------
  // お気に入り（☆）
  //   保存先の切り替え（ログイン中は DB、未ログインはブラウザ）は SpotiveFavorites が行う
  // -----------------------------------------------------------------
  const favoriteButtons = document.querySelectorAll("[data-favorite-team]");
  if (favoriteButtons.length > 0 && typeof SpotiveFavorites !== "undefined") {
    const renderFavorites = () => {
      const favorites = SpotiveFavorites.teamIds();
      favoriteButtons.forEach((button) => {
        const on = favorites.includes(button.dataset.favoriteTeam);
        button.querySelector(".match-detail__favorite-icon").textContent = on ? "★" : "☆";
        button.classList.toggle("is-active", on);
        button.setAttribute("aria-pressed", on ? "true" : "false");
      });
    };

    favoriteButtons.forEach((button) => {
      button.addEventListener("click", () => {
        SpotiveFavorites.toggle(button.dataset.favoriteTeam);
        renderFavorites();
      });
    });
    // 保存に失敗して元に戻したときや、ブラウザの登録をアカウントに移したときに描き直す
    SpotiveFavorites.onChange(renderFavorites);
    renderFavorites();
  }

  // -----------------------------------------------------------------
  // ✕（閉じる）
  //   同じサイトの画面から来たときは、その画面に戻る（一覧のスクロール位置も戻る）。
  //   URL を直接開いたときなどは、リンク先（地図）へそのまま移る
  // -----------------------------------------------------------------
  const close = document.querySelector("[data-back]");
  if (close) {
    close.addEventListener("click", (e) => {
      // 地図の詳細シートの中にいるときは、シートを閉じてもらう（js/pages/map.js）
      if (window.parent !== window) {
        e.preventDefault();
        window.parent.postMessage({ type: "spotive:detail-close" }, location.origin);
        return;
      }

      let fromSameSite = false;
      try {
        fromSameSite = document.referrer !== "" && new URL(document.referrer).origin === location.origin;
      } catch {
        fromSameSite = false;
      }
      if (fromSameSite && history.length > 1) {
        e.preventDefault();
        history.back();
      }
    });
  }

  // -----------------------------------------------------------------
  // 現在地から会場まで
  //   開いただけで位置情報の許可を求めると驚かせるので、すでに許可されているときだけ出す
  // -----------------------------------------------------------------
  const distance = document.querySelector("[data-distance][data-lat]");
  if (!distance || !navigator.geolocation || !navigator.permissions) return;

  const toRad = (deg) => (deg * Math.PI) / 180;
  /** 2 点間の距離（km）。地球を球とみなした近似 */
  function distanceKm(lat1, lng1, lat2, lng2) {
    const a = Math.sin(toRad(lat2 - lat1) / 2) ** 2
      + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(toRad(lng2 - lng1) / 2) ** 2;
    return 6371 * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
  }

  navigator.permissions
    .query({ name: "geolocation" })
    .then((status) => {
      if (status.state !== "granted") return;
      navigator.geolocation.getCurrentPosition((pos) => {
        const km = distanceKm(
          pos.coords.latitude, pos.coords.longitude,
          Number(distance.dataset.lat), Number(distance.dataset.lng)
        );
        distance.textContent = km < 1
          ? `約${Math.round(km * 100) * 10}m`
          : `約${km < 10 ? km.toFixed(1) : Math.round(km)}km`;
      });
    })
    .catch(() => {
      // 問い合わせに失敗しても、距離を出さないだけでよい
    });
});
