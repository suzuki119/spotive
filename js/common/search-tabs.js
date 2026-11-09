/**
 * search-tabs.js
 * 競技のタブ（.search-tabs）を横にスクロールできることを分かりやすくする。
 * 試合を探す画面（js/pages/match-search.js）と試合一覧（js/pages/match-list.js）で使う。
 *
 *   ・続きがある側の端を薄くする（is-more-start / is-more-end。見た目は _match-search.scss）
 *   ・選んでいる競技が画面の外にあるときは、開いたときに見える位置までスクロールする
 */

document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll(".search-tabs").forEach((tabs) => {
    /** 左右に続きがあるかを見て、端を薄くするクラスを付け外しする */
    const update = () => {
      const max = tabs.scrollWidth - tabs.clientWidth;
      // 小数のずれで端に着いても消えないことがあるので、1px の余裕を持たせる
      tabs.classList.toggle("is-more-start", tabs.scrollLeft > 1);
      tabs.classList.toggle("is-more-end", tabs.scrollLeft < max - 1);
    };

    // 選んでいる競技を見える位置に寄せる（ページ全体は動かさない）
    const checked = tabs.querySelector(".search-tabs__input:checked");
    const tab = checked ? checked.closest(".search-tabs__tab") : null;
    if (tab && tab.offsetLeft + tab.offsetWidth > tabs.clientWidth) {
      tabs.scrollLeft = tab.offsetLeft - tabs.clientWidth / 2 + tab.offsetWidth / 2;
    }

    update();
    tabs.addEventListener("scroll", update, { passive: true });
    // 画面の向きや幅が変わると、続きがあるかも変わる
    if ("ResizeObserver" in window) {
      new ResizeObserver(update).observe(tabs);
    } else {
      window.addEventListener("resize", update);
    }
  });
});
