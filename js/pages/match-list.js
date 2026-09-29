/**
 * match-list.js
 * 試合一覧画面（pages/match/match-list.php）の処理。
 * お気に入りのチームの試合に印を付け、「だけ見る」で絞り込む。
 */

document.addEventListener("DOMContentLoaded", () => {
  const onlyToggle = document.getElementById("favorite-only");
  if (!onlyToggle) return;   // 試合が 0 件のときは切り替えを出していない

  const empty = document.getElementById("favorite-empty");

  // お気に入りの試合に印を付ける（登録はお気に入り画面で行う）
  function markFavorites() {
    const favorites = SpotiveFavorites.teamIds();
    document.querySelectorAll("[data-match]").forEach((item) => {
      const card = item.querySelector(".match-card");
      const isFavorite = SpotiveFavorites.isFavoriteMatch(card, favorites);
      item.dataset.favorite = isFavorite ? "1" : "0";
      card.querySelector(".match-card__favorite").classList.toggle("is-hidden", !isFavorite);
    });
  }

  function applyFilter() {
    const only = onlyToggle.checked;
    let shown = 0;

    document.querySelectorAll("[data-day]").forEach((day) => {
      let dayShown = 0;
      day.querySelectorAll("[data-match]").forEach((item) => {
        const visible = !only || item.dataset.favorite === "1";
        item.classList.toggle("is-hidden", !visible);
        if (visible) dayShown += 1;
      });
      // 試合が 1 つも残らない日は、日付ごと隠す
      day.classList.toggle("is-hidden", dayShown === 0);
      shown += dayShown;
    });

    empty.classList.toggle("is-hidden", shown > 0);
  }

  onlyToggle.addEventListener("change", applyFilter);

  // ブラウザの登録をアカウントに移し終えたら、印と絞り込みを付け直す
  SpotiveFavorites.onChange(() => {
    markFavorites();
    applyFilter();
  });

  markFavorites();
  applyFilter();
});
