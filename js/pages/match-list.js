/**
 * match-list.js
 * 試合一覧画面（pages/match/match-list.php）の処理。
 * 競技のタブを選んだら表示し直し、お気に入りのチームの試合に印を付け、「だけ見る」で絞り込む。
 */

document.addEventListener("DOMContentLoaded", () => {
  // 競技のタブ。選んだらすぐに表示し直す（「だけ見る」の状態も一緒に送る）
  const form = document.getElementById("schedule-form");
  if (form) {
    form.querySelectorAll(".search-tabs__input").forEach((tab) => {
      tab.addEventListener("change", () => form.requestSubmit());
    });
  }

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

  // 「だけ見る」はその場で絞り込む。再読み込みしても残るよう、URL の favorite=1 も書き換えておく
  onlyToggle.addEventListener("change", () => {
    applyFilter();
    const url = new URL(window.location.href);
    if (onlyToggle.checked) {
      url.searchParams.set("favorite", "1");
    } else {
      url.searchParams.delete("favorite");
    }
    history.replaceState(null, "", url);
  });

  // ブラウザの登録をアカウントに移し終えたら、印と絞り込みを付け直す
  SpotiveFavorites.onChange(() => {
    markFavorites();
    applyFilter();
  });

  markFavorites();
  applyFilter();
});
