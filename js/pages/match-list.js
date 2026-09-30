/**
 * match-list.js
 * 日程画面（pages/match/match-list.php）の処理。
 *   ・競技のタブを選んだら表示し直す（選んでいる日と「だけ見る」も一緒に送る）
 *   ・お気に入りのチームの試合に印を付け、「だけ見る」で絞り込む
 *   ・1 日分だけを見せる。「‹ ›」は試合のある日へ飛び、「カレンダー」で日を選べる
 *   ・カレンダーは試合のある日に点を付け、日曜・祝日と土曜に色を付ける
 *     （祝日は Holidays JP API。取れなくても色が付かないだけで、ほかは動く）
 */

document.addEventListener("DOMContentLoaded", () => {
  const WEEK = ["日", "月", "火", "水", "木", "金", "土"];

  /** Date → 2026-10-03（端末の時刻で） */
  const ymd = (d) =>
    `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
  const parseYmd = (s) => {
    const [y, m, d] = s.split("-").map(Number);
    return new Date(y, m - 1, d);
  };
  const today = ymd(new Date());
  const tomorrow = (() => {
    const d = new Date();
    d.setDate(d.getDate() + 1);
    return ymd(d);
  })();
  /** 今日 / 明日 / 10/3(土) */
  function dayLabel(s) {
    if (s === today) return "今日";
    if (s === tomorrow) return "明日";
    const d = parseYmd(s);
    return `${d.getMonth() + 1}/${d.getDate()}(${WEEK[d.getDay()]})`;
  }

  const form = document.getElementById("schedule-form");
  const dateInput = document.querySelector("[data-date-input]");
  const onlyToggle = document.getElementById("favorite-only");
  const favoriteEmpty = document.getElementById("favorite-empty");
  const days = [...document.querySelectorAll("[data-day]")];

  // 選んでいる日。URL の ?date= が今日以降ならそれ、無ければ今日
  let selectedDate = dateInput && dateInput.value >= today ? dateInput.value : today;

  // -----------------------------------------------------------------
  // 競技のタブ。選んだらすぐに表示し直す
  // -----------------------------------------------------------------
  if (form) {
    form.querySelectorAll(".search-tabs__input").forEach((tab) => {
      tab.addEventListener("change", () => {
        if (dateInput) dateInput.value = selectedDate === today ? "" : selectedDate;
        form.requestSubmit();
      });
    });
  }

  if (days.length === 0) return;   // 試合が 0 件のときは、日の切り替えも出さない

  // -----------------------------------------------------------------
  // お気に入り（印と「だけ見る」）
  // -----------------------------------------------------------------
  function markFavorites() {
    const favorites = SpotiveFavorites.teamIds();
    document.querySelectorAll("[data-match]").forEach((item) => {
      const card = item.querySelector(".match-card");
      const isFavorite = SpotiveFavorites.isFavoriteMatch(card, favorites);
      item.dataset.favorite = isFavorite ? "1" : "0";
      card.querySelector(".match-card__favorite").classList.toggle("is-hidden", !isFavorite);
    });
  }

  /** いまの絞り込み（だけ見る）で出す試合か */
  const itemVisible = (item) => !(onlyToggle && onlyToggle.checked) || item.dataset.favorite === "1";

  /** 試合のある日（絞り込み込み）。並びは日付順 */
  function matchDates() {
    return days
      .filter((day) => [...day.querySelectorAll("[data-match]")].some(itemVisible))
      .map((day) => day.dataset.date);
  }

  // -----------------------------------------------------------------
  // 日の切り替え
  // -----------------------------------------------------------------
  const label = document.querySelector("[data-day-label]");
  const count = document.querySelector("[data-day-count]");
  const prev = document.querySelector('[data-day-step="-1"]');
  const next = document.querySelector('[data-day-step="1"]');
  const none = document.querySelector("[data-day-none]");
  const noneText = document.querySelector("[data-day-none-text]");
  const noneNext = document.querySelector("[data-day-next]");

  function render() {
    const dates = matchDates();
    let shown = 0;

    days.forEach((day) => {
      const isSelected = day.dataset.date === selectedDate;
      let dayShown = 0;
      day.querySelectorAll("[data-match]").forEach((item) => {
        const visible = isSelected && itemVisible(item);
        item.classList.toggle("is-hidden", !visible);
        if (visible) dayShown += 1;
      });
      day.classList.toggle("is-hidden", dayShown === 0);
      shown += dayShown;
    });

    label.textContent = dayLabel(selectedDate);
    count.textContent = String(shown);
    // ‹ は今日まで、› はこの先に試合のある日があるときだけ
    prev.disabled = selectedDate <= today;
    next.disabled = !dates.some((d) => d > selectedDate);

    // 「だけ見る」でお気に入りの試合が 1 つも無いときは、登録への案内を出す
    const noFavorites = Boolean(onlyToggle && onlyToggle.checked && dates.length === 0);
    if (favoriteEmpty) favoriteEmpty.classList.toggle("is-hidden", !noFavorites);

    none.hidden = shown > 0 || noFavorites;
    if (shown === 0 && !noFavorites) {
      noneText.textContent = `${dayLabel(selectedDate)}の試合はありません。`;
      const upcoming = dates.find((d) => d > selectedDate);
      noneNext.hidden = !upcoming;
      if (upcoming) {
        noneNext.textContent = `次の試合（${dayLabel(upcoming)}）へ`;
        noneNext.dataset.date = upcoming;
      }
    }

    renderCalendar();
  }

  function selectDate(date) {
    selectedDate = date < today ? today : date;
    render();
    // 再読み込みしても同じ日を見られるよう、URL も書き換えておく
    const url = new URL(window.location.href);
    if (selectedDate === today) {
      url.searchParams.delete("date");
    } else {
      url.searchParams.set("date", selectedDate);
    }
    history.replaceState(null, "", url);
  }

  // ‹ › は試合のある日へ飛ぶ（無い日は飛ばす）。‹ で前に無ければ今日に戻る
  next.addEventListener("click", () => {
    const target = matchDates().find((d) => d > selectedDate);
    if (target) selectDate(target);
  });
  prev.addEventListener("click", () => {
    const before = matchDates().filter((d) => d < selectedDate && d >= today);
    selectDate(before.length ? before[before.length - 1] : today);
  });
  noneNext.addEventListener("click", () => {
    if (noneNext.dataset.date) selectDate(noneNext.dataset.date);
  });

  if (onlyToggle) {
    onlyToggle.addEventListener("change", () => {
      render();
      const url = new URL(window.location.href);
      if (onlyToggle.checked) {
        url.searchParams.set("favorite", "1");
      } else {
        url.searchParams.delete("favorite");
      }
      history.replaceState(null, "", url);
    });
  }

  // -----------------------------------------------------------------
  // カレンダー（下から出るシート）
  // -----------------------------------------------------------------
  const sheet = document.querySelector("[data-calendar]");
  const grid = document.querySelector("[data-calendar-grid]");
  const monthLabel = document.querySelector("[data-month-label]");
  const openButton = document.querySelector("[data-calendar-open]");
  const prevMonth = document.querySelector('[data-month-step="-1"]');
  let shownMonth = new Date(parseYmd(selectedDate).getFullYear(), parseYmd(selectedDate).getMonth(), 1);
  let holidays = {};   // 2026-11-03 => "文化の日"

  function renderCalendar() {
    if (sheet.hidden) return;
    // 描き直すとボタンが作り直されるので、カレンダーの中にあったフォーカスを覚えておく
    const focusedDate = grid.contains(document.activeElement) ? document.activeElement.dataset.date : null;
    const dates = new Set(matchDates());
    const year = shownMonth.getFullYear();
    const month = shownMonth.getMonth();
    monthLabel.textContent = `${year}年${month + 1}月`;

    // 今月より前には戻らない（終わった試合のデータは無い）
    const now = new Date();
    prevMonth.disabled = year < now.getFullYear() || (year === now.getFullYear() && month <= now.getMonth());

    const cells = [];
    const firstWeekday = new Date(year, month, 1).getDay();
    for (let i = 0; i < firstWeekday; i += 1) {
      const blank = document.createElement("span");
      blank.className = "calendar-sheet__blank";
      cells.push(blank);
    }

    const lastDay = new Date(year, month + 1, 0).getDate();
    for (let d = 1; d <= lastDay; d += 1) {
      const date = ymd(new Date(year, month, d));
      const weekday = new Date(year, month, d).getDay();
      const holiday = holidays[date];
      const button = document.createElement("button");
      button.type = "button";
      button.className = "calendar-sheet__day";
      button.textContent = String(d);
      button.dataset.date = date;

      if (weekday === 0 || holiday) button.classList.add("is-sunday");
      if (weekday === 6 && !holiday) button.classList.add("is-saturday");
      if (date === today) button.classList.add("is-today");
      if (date === selectedDate) button.classList.add("is-selected");
      if (dates.has(date)) button.classList.add("has-match");

      // 読み上げ用。「10月3日(土)、試合あり」
      const parts = [`${month + 1}月${d}日(${WEEK[weekday]})`];
      if (holiday) parts.push(holiday);
      parts.push(dates.has(date) ? "試合あり" : "試合なし");
      button.setAttribute("aria-label", parts.join("、"));
      if (date === selectedDate) button.setAttribute("aria-current", "date");

      if (date < today) button.disabled = true;   // 終わった日は選べない
      cells.push(button);
    }
    grid.replaceChildren(...cells);
    if (focusedDate) grid.querySelector(`[data-date="${focusedDate}"]`)?.focus();
  }

  function openCalendar() {
    shownMonth = new Date(parseYmd(selectedDate).getFullYear(), parseYmd(selectedDate).getMonth(), 1);
    sheet.hidden = false;
    document.body.classList.add("is-sheet-open");
    renderCalendar();
    loadHolidays(shownMonth.getFullYear());
    (grid.querySelector(".is-selected") || grid.querySelector("button:not(:disabled)"))?.focus();
  }

  function closeCalendar() {
    if (sheet.hidden) return;
    sheet.hidden = true;
    document.body.classList.remove("is-sheet-open");
    openButton.focus();
  }

  openButton.addEventListener("click", openCalendar);
  sheet.querySelectorAll("[data-calendar-close]").forEach((el) => el.addEventListener("click", closeCalendar));
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") closeCalendar();
  });

  sheet.querySelectorAll("[data-month-step]").forEach((button) => {
    button.addEventListener("click", () => {
      shownMonth = new Date(shownMonth.getFullYear(), shownMonth.getMonth() + Number(button.dataset.monthStep), 1);
      renderCalendar();
      loadHolidays(shownMonth.getFullYear());
    });
  });

  grid.addEventListener("click", (e) => {
    const button = e.target.closest(".calendar-sheet__day");
    if (!button || button.disabled) return;
    selectDate(button.dataset.date);
    closeCalendar();
  });

  // -----------------------------------------------------------------
  // 祝日（Holidays JP API。キー不要）。同じ年は 1 回だけ取る
  // -----------------------------------------------------------------
  const loadedYears = new Set();
  function loadHolidays(year) {
    if (loadedYears.has(year)) return;
    loadedYears.add(year);
    fetch(`https://holidays-jp.github.io/api/v1/${year}/date.json`)
      .then((res) => (res.ok ? res.json() : {}))
      .then((data) => {
        if (data && typeof data === "object") {
          holidays = { ...holidays, ...data };
          renderCalendar();
        }
      })
      .catch(() => {
        // 取れなくても、祝日の色が付かないだけでよい
      });
  }

  // ブラウザの登録をアカウントに移し終えたら、印と絞り込みを付け直す
  SpotiveFavorites.onChange(() => {
    markFavorites();
    render();
  });

  markFavorites();
  render();
});
