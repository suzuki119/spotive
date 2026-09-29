/**
 * navigation.js
 * 画面下のメニューバー（pages/menu-bar.php）の処理。
 * 「メニュー」を押すと上の段が開き、アイコンが × に変わる。
 * menu-bar.php が自分で読み込むので、各ページで <script> を足す必要はない。
 */

document.addEventListener("DOMContentLoaded", () => {
  const toggle = document.querySelector(".menu-bar__toggle");
  const sub = document.getElementById("menu-bar-sub");
  if (!toggle || !sub) return;

  const icon = toggle.querySelector(".menu-bar__icon");

  function setOpen(open) {
    sub.hidden = !open;
    toggle.setAttribute("aria-expanded", open ? "true" : "false");
    toggle.classList.toggle("is-open", open);
    icon.src = open ? toggle.dataset.iconClose : toggle.dataset.iconOpen;
  }

  toggle.addEventListener("click", () => setOpen(sub.hidden));

  // メニューの外を押したとき・Esc を押したときも閉じる
  document.addEventListener("click", (e) => {
    if (!sub.hidden && !e.target.closest(".menu-bar")) setOpen(false);
  });
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && !sub.hidden) {
      setOpen(false);
      toggle.focus();
    }
  });
});
