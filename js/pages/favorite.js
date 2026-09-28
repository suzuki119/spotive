/**
 * favorite.js
 * お気に入り画面（pages/favorite/favorite.php）の処理。
 * チームの ☆ で登録・解除し、登録したチームの試合だけを上に出す。
 */

document.addEventListener("DOMContentLoaded", () => {
  const toggles = document.querySelectorAll(".team-item__toggle");
  const empty = document.getElementById("favorite-empty");

  /** ☆ の見た目と、上に出す試合を今の登録内容にそろえる */
  function render() {
    const favorites = SpotiveFavorites.teamIds();

    toggles.forEach((button) => {
      const on = favorites.includes(button.dataset.teamId);
      button.textContent = on ? "★" : "☆";
      button.classList.toggle("is-active", on);
      button.setAttribute("aria-pressed", on ? "true" : "false");
    });

    let shown = 0;
    document.querySelectorAll("[data-day]").forEach((day) => {
      let dayShown = 0;
      day.querySelectorAll("[data-match]").forEach((item) => {
        const visible = SpotiveFavorites.isFavoriteMatch(item.querySelector(".match-card"), favorites);
        item.classList.toggle("is-hidden", !visible);
        if (visible) dayShown += 1;
      });
      day.classList.toggle("is-hidden", dayShown === 0);
      shown += dayShown;
    });

    empty.textContent = favorites.length === 0
      ? "下の「チーム」から ☆ を押して登録すると、そのチームの試合がここに並びます。"
      : "登録したチームの、これからの試合はありません。";
    empty.classList.toggle("is-hidden", shown > 0);
  }

  toggles.forEach((button) => {
    button.addEventListener("click", () => {
      SpotiveFavorites.toggle(button.dataset.teamId);
      render();
    });
  });

  // ログイン中の保存に失敗したときや、ブラウザの登録をアカウントに移したときに描き直す
  SpotiveFavorites.onChange(render);
  render();
});
