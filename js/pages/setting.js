/**
 * setting.js
 * マイページ（pages/setting/setting.php）の処理。
 *   ・Records のタブ（B.LEAGUE / プロ野球 / …）で、観戦記録を競技ごとに切り替える
 */

document.addEventListener("DOMContentLoaded", () => {
  const tabs = document.querySelectorAll("[data-record-tab]");
  const items = document.querySelectorAll("[data-record-sport]");
  if (!tabs.length) return;

  tabs.forEach((tab) => {
    tab.addEventListener("click", () => {
      const sport = tab.dataset.recordTab;
      tabs.forEach((t) => {
        const on = t === tab;
        t.classList.toggle("is-active", on);
        t.setAttribute("aria-selected", on ? "true" : "false");
      });
      items.forEach((item) => {
        item.hidden = item.dataset.recordSport !== sport;
      });
    });
  });
});
