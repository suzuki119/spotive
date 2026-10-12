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
  let iconTimer = null;

  function setOpen(open) {
    sub.classList.toggle("is-open", open);
    toggle.setAttribute("aria-expanded", open ? "true" : "false");

    // アイコンを一度消してから差し替え、また出す（_menu-bar.scss の .is-changing）
    toggle.classList.add("is-changing");
    clearTimeout(iconTimer);
    iconTimer = setTimeout(() => {
      icon.src = open ? toggle.dataset.iconClose : toggle.dataset.iconOpen;
      toggle.classList.remove("is-changing");
    }, 200);
  }

  const isOpen = () => sub.classList.contains("is-open");

  toggle.addEventListener("click", () => setOpen(!isOpen()));

  // メニューの外を押したとき・Esc を押したときも閉じる
  document.addEventListener("click", (e) => {
    if (isOpen() && !e.target.closest(".menu-bar")) setOpen(false);
  });
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && isOpen()) {
      setOpen(false);
      toggle.focus();
    }
  });
});
