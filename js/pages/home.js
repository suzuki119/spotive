/**
 * home.js
 * ホーム画面（pages/home/home.php）の処理。
 *   ・お気に入りのチームの試合だけを「お気に入り」に出す
 *   ・位置情報がすでに許可されていれば、会場までの距離を出す
 */

document.addEventListener("DOMContentLoaded", () => {
  // -----------------------------------------------------------------
  // お気に入り
  // -----------------------------------------------------------------
  const slides = document.querySelectorAll("[data-favorite-slide]");
  const empty = document.getElementById("home-favorite-empty");

  function renderFavorites() {
    const favorites = SpotiveFavorites.teamIds();
    let shown = 0;
    slides.forEach((slide) => {
      const visible = SpotiveFavorites.isFavoriteMatch(slide.querySelector(".fav-match"), favorites);
      slide.classList.toggle("is-hidden", !visible);
      if (visible) shown += 1;
    });
    empty.classList.toggle("is-hidden", shown > 0);
  }

  renderFavorites();
  // ログイン直後にブラウザ側の登録をアカウントへ移したときなど、あとから変わることがある
  SpotiveFavorites.onChange(renderFavorites);

  // -----------------------------------------------------------------
  // 会場までの距離
  //   ホームを開いただけで位置情報の許可を求めると驚かせるので、
  //   すでに許可されているときだけ出す（許可は地図の「現在地」ボタンで求める）
  // -----------------------------------------------------------------
  const cards = document.querySelectorAll(".now-match[data-lat]");
  if (!cards.length || !navigator.geolocation || !navigator.permissions) return;

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
        const { latitude, longitude } = pos.coords;
        cards.forEach((card) => {
          const km = distanceKm(latitude, longitude, Number(card.dataset.lat), Number(card.dataset.lng));
          const box = card.querySelector("[data-distance]");
          box.querySelector("[data-distance-text]").textContent =
            `${km < 10 ? km.toFixed(1) : Math.round(km)}km`;
          box.classList.remove("is-hidden");
        });
      });
    })
    .catch(() => {
      // 問い合わせに失敗しても、距離を出さないだけでよい
    });
});
