/**
 * home.js
 * ホーム画面（pages/home/home.php）の処理。
 *   ・お気に入り … 「‹ 今日 ›」で日付を、タブでお気に入りのチームを切り替え、その試合だけを出す。
 *                  試合が 2 つ以上なら横にめくれて、下の点で何枚目かを示す
 *   ・今から観戦できる試合 … 「現在地から○km以内」で絞り込む。会場までの距離も出す
 * 現在地はブラウザの中だけで使い、サーバーには送らない。
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
  const addDays = (s, n) => {
    const d = parseYmd(s);
    d.setDate(d.getDate() + n);
    return ymd(d);
  };
  /** 今日 / 明日 / 10/3(土) */
  function dayLabel(s) {
    const today = ymd(new Date());
    if (s === today) return "今日";
    if (s === addDays(today, 1)) return "明日";
    const d = parseYmd(s);
    return `${d.getMonth() + 1}/${d.getDate()}(${WEEK[d.getDay()]})`;
  }

  // =================================================================
  // お気に入り
  // =================================================================
  const browser = document.querySelector("[data-fav-browser]");
  const empty = document.getElementById("home-favorite-empty");
  const slider = document.querySelector("[data-fav-slider]");
  const slides = [...document.querySelectorAll("[data-favorite-slide]")];
  const tabsBox = document.querySelector("[data-team-tabs]");
  const dayText = document.querySelector("[data-day-label]");
  const prevDay = document.querySelector('[data-day-step="-1"]');
  const nextDay = document.querySelector('[data-day-step="1"]');
  const none = document.querySelector("[data-fav-none]");
  const noneText = document.querySelector("[data-fav-none-text]");
  const nextButton = document.querySelector("[data-fav-next]");
  const dots = document.querySelector("[data-fav-dots]");

  let teamNames = {};
  try {
    teamNames = JSON.parse(document.getElementById("team-names").textContent) || {};
  } catch {
    teamNames = {};
  }

  const today = ymd(new Date());
  let selectedDate = today;
  let selectedTeam = null;

  const slideTeams = (slide) => (slide.querySelector(".fav-match").dataset.teamIds || "").split(" ");

  /** 選んだチームの、指定した日より後で一番近い試合の日 */
  function nextMatchDate(teamId, after) {
    const dates = slides
      .filter((s) => s.dataset.date > after && slideTeams(s).includes(teamId))
      .map((s) => s.dataset.date)
      .sort();
    return dates[0] || null;
  }

  /** 選んだチームの、指定した日より前で一番近い試合の日（今日より前は見ない） */
  function prevMatchDate(teamId, before) {
    const dates = slides
      .filter((s) => s.dataset.date < before && s.dataset.date >= today && slideTeams(s).includes(teamId))
      .map((s) => s.dataset.date)
      .sort();
    return dates[dates.length - 1] || null;
  }

  function renderTabs(favorites) {
    tabsBox.replaceChildren(...favorites.map((id) => {
      const tab = document.createElement("button");
      tab.type = "button";
      tab.className = "fav-browser__tab";
      tab.setAttribute("role", "tab");
      tab.textContent = teamNames[id] || id;   // textContent で入れる（HTML として解釈させない）
      const on = id === selectedTeam;
      tab.classList.toggle("is-active", on);
      tab.setAttribute("aria-selected", on ? "true" : "false");
      tab.addEventListener("click", () => {
        selectedTeam = id;
        render();
      });
      return tab;
    }));
  }

  function renderDots(count) {
    dots.replaceChildren(...Array.from({ length: count > 1 ? count : 0 }, (_, i) => {
      const dot = document.createElement("span");
      dot.className = "fav-browser__dot";
      dot.classList.toggle("is-active", i === 0);
      return dot;
    }));
  }

  function render() {
    const favorites = SpotiveFavorites.teamIds().filter((id) => teamNames[id]);
    const hasFavorites = favorites.length > 0;
    empty.classList.toggle("is-hidden", hasFavorites);
    browser.hidden = !hasFavorites;
    if (!hasFavorites) return;

    if (!favorites.includes(selectedTeam)) selectedTeam = favorites[0];
    renderTabs(favorites);

    dayText.textContent = dayLabel(selectedDate);
    prevDay.disabled = selectedDate <= today;   // 終わった試合のデータは無いので、今日より前には戻らない
    nextDay.disabled = nextMatchDate(selectedTeam, selectedDate) === null;   // この先に試合が無ければ進めない

    let shown = 0;
    slides.forEach((slide) => {
      const visible = slide.dataset.date === selectedDate && slideTeams(slide).includes(selectedTeam);
      slide.hidden = !visible;
      if (visible) shown += 1;
    });
    slider.scrollLeft = 0;
    renderDots(shown);

    none.hidden = shown > 0;
    if (shown === 0) {
      const name = teamNames[selectedTeam] || "";
      noneText.textContent = `${dayLabel(selectedDate)}は${name}の試合はありません。`;
      const next = nextMatchDate(selectedTeam, selectedDate);
      nextButton.hidden = next === null;
      if (next) {
        nextButton.textContent = `次の試合（${dayLabel(next)}）へ`;
        nextButton.dataset.date = next;
      }
    }
  }

  // ‹ › は、選んだチームの試合がある日まで飛ぶ（試合の無い日は飛ばす）。
  // ‹ で前に試合が無ければ「今日」に戻る
  nextDay.addEventListener("click", () => {
    const next = nextMatchDate(selectedTeam, selectedDate);
    if (next === null) return;
    selectedDate = next;
    render();
  });
  prevDay.addEventListener("click", () => {
    if (selectedDate <= today) return;
    selectedDate = prevMatchDate(selectedTeam, selectedDate) ?? today;
    render();
  });

  nextButton.addEventListener("click", () => {
    if (!nextButton.dataset.date) return;
    selectedDate = nextButton.dataset.date;
    render();
  });

  // めくった位置に合わせて、点の色を変える
  slider.addEventListener("scroll", () => {
    const width = slider.clientWidth || 1;
    const index = Math.round(slider.scrollLeft / width);
    dots.querySelectorAll(".fav-browser__dot").forEach((dot, i) => dot.classList.toggle("is-active", i === index));
  }, { passive: true });

  render();
  // ログイン直後にブラウザ側の登録をアカウントへ移したときなど、あとから変わることがある
  SpotiveFavorites.onChange(render);

  // =================================================================
  // 今から観戦できる試合（距離の表示と「現在地から○km以内」）
  // =================================================================
  const list = document.querySelector("[data-now-list]");
  if (!list) return;

  const limit = Number(list.dataset.limit) || 10;
  const items = [...list.querySelectorAll("[data-now-item]")];
  const radius = document.querySelector("[data-radius]");
  const status = document.querySelector("[data-radius-status]");
  const nowEmpty = document.querySelector("[data-now-empty]");
  let position = null;   // { lat, lng }。分かるまでは null

  const toRad = (deg) => (deg * Math.PI) / 180;
  /** 2 点間の距離（km）。地球を球とみなした近似 */
  function distanceKm(lat1, lng1, lat2, lng2) {
    const a = Math.sin(toRad(lat2 - lat1) / 2) ** 2
      + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(toRad(lng2 - lng1) / 2) ** 2;
    return 6371 * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
  }

  /** 試合までの距離（km）。会場の場所か現在地が分からなければ null */
  function kmOf(item) {
    const card = item.querySelector(".now-match");
    if (!position || card.dataset.lat == null) return null;
    return distanceKm(position.lat, position.lng, Number(card.dataset.lat), Number(card.dataset.lng));
  }

  function showStatus(text) {
    status.textContent = text;
    status.hidden = text === "";
  }

  function renderNow() {
    const km = Number(radius.value);
    let shown = 0;
    items.forEach((item) => {
      const d = kmOf(item);
      // 距離を出す
      const box = item.querySelector("[data-distance]");
      if (d !== null) {
        box.querySelector("[data-distance-text]").textContent = `${d < 10 ? d.toFixed(1) : Math.round(d)}km`;
        box.classList.remove("is-hidden");
      }
      // 範囲を選んでいて現在地が分かっているときは、範囲の中の試合だけ
      const inRange = !km || !position || (d !== null && d <= km);
      const visible = inRange && shown < limit;
      item.hidden = !visible;
      if (visible) shown += 1;
    });
    nowEmpty.hidden = shown > 0;
  }

  function locate(onDone) {
    if (!navigator.geolocation) {
      showStatus("この端末では現在地を使えないため、距離で絞り込めません。");
      return;
    }
    showStatus("現在地を確認しています…");
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        position = { lat: pos.coords.latitude, lng: pos.coords.longitude };
        showStatus("");
        onDone();
      },
      () => {
        showStatus("現在地を取得できなかったため、すべての試合を表示しています（位置情報の許可を確認してください）。");
        radius.value = "";
        renderNow();
      },
      { timeout: 10000, maximumAge: 5 * 60 * 1000 }
    );
  }

  // 範囲を選んだら、そのときに初めて現在地を求める（開いただけでは許可を求めない）
  radius.addEventListener("change", () => {
    if (radius.value && !position) {
      locate(renderNow);
      return;
    }
    renderNow();
  });

  // 位置情報がすでに許可されていれば、最初から距離を出す
  if (navigator.permissions && navigator.geolocation) {
    navigator.permissions
      .query({ name: "geolocation" })
      .then((state) => {
        if (state.state === "granted") locate(renderNow);
      })
      .catch(() => {
        // 問い合わせに失敗しても、距離を出さないだけでよい
      });
  }
});
