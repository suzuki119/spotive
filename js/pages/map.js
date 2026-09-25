/**
 * map.js
 * 観戦マップ画面（pages/map/map.php）の処理。
 * Leaflet 1.9.4 を使う。バージョンは各自で変えないこと。
 *
 * 表示する試合は次の 2 つを合流させる。
 *   1. data/matches.json（仮データ。DB ができるまでの置き換え用）
 *   2. map.php が v_public_tournaments から埋め込んだ大会（#map-tournaments）
 * 会場の座標・都道府県・エリアは data/venues.json（会場マスタ）で補う。
 *
 * 背景地図は地理院タイル（日本のみ配信）が既定。日本の外はタイルが無いので、
 * 地図の下地の色がそのまま海として見える。世界を見たいときは
 * 右上の切り替えで OpenStreetMap を選ぶ。
 */

document.addEventListener("DOMContentLoaded", () => {
  const mapEl = document.getElementById("map");
  if (!mapEl) return;

  const $ = (sel) => document.querySelector(sel);
  const esc = (s) =>
    String(s).replace(/[&<>"']/g, (c) => ({
      "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;",
    })[c]);
  const yen = (n) => "¥" + n.toLocaleString("ja-JP");

  const WEEK = ["日", "月", "火", "水", "木", "金", "土"];
  const PRICE_RANGES = {
    u2000: [0, 2000],
    "2to4": [2001, 4000],
    "4to6": [4001, 6000],
    o6: [6001, Infinity],
  };
  // AGENTS.md のブレイクポイント（タブレット 768px）に合わせる。
  // これ未満がスマホ表示＝絞り込みと詳細を下から出すシートで扱う
  const MOBILE = window.matchMedia("(max-width: 767px)");
  const POPUP_GAME_LIMIT = 5;

  // 地図の拡大率
  const START_ZOOM = 5;        // 日本全体（現在地が使えないとき）
  const LOCATE_ZOOM = 13;      // 現在地を中心にしたとき。まわりの街が分かるくらい
  const FOCUS_ZOOM = 16;       // 一覧から試合を選んだとき。会場が特定できるくらい
  const FLY_DURATION = 2.2;    // 秒。一覧から選んだ試合へ飛ぶのにかける時間。
                               // いちばん引いたところで県名が読める程度にゆっくり見せる

  // 競技コードは index.php の SPORT_LABELS と揃える。色とアイコンは地図の見た目用
  const SPORTS = {
    soccer:      { label: "サッカー",       color: "#2f9e5b", icon: "⚽" },
    baseball:    { label: "野球",           color: "#e5484d", icon: "⚾" },
    basketball:  { label: "バスケットボール", color: "#ef7a1a", icon: "🏀" },
    volleyball:  { label: "バレーボール",   color: "#3e63dd", icon: "🏐" },
    futsal:      { label: "フットサル",     color: "#26a269", icon: "⚽" },
    tennis:      { label: "テニス",         color: "#c2410c", icon: "🎾" },
    badminton:   { label: "バドミントン",   color: "#0f9b8e", icon: "🏸" },
    rugby:       { label: "ラグビー",       color: "#8e4ec6", icon: "🏉" },
    tabletennis: { label: "卓球",           color: "#0284c7", icon: "🏓" },
    other:       { label: "その他",         color: "#6f6e77", icon: "🏅" },
  };
  const sportOf = (key) => SPORTS[key] || SPORTS.other;

  // 自由入力の競技名（tournaments.sport は VARCHAR）を SPORTS のキーに寄せる
  const SPORT_KEYS = [
    [/野球|ベースボール|baseball/i, "baseball"],
    [/フットサル|futsal/i, "futsal"],
    [/サッカー|フット|soccer|football/i, "soccer"],
    [/バスケ|basketball/i, "basketball"],
    [/バレー|volleyball/i, "volleyball"],
    [/ラグビー|rugby/i, "rugby"],
    [/卓球|table.?tennis/i, "tabletennis"],
    [/バドミントン|badminton/i, "badminton"],
    [/テニス|tennis/i, "tennis"],
  ];
  const sportKey = (name) =>
    SPORTS[name] ? name : (SPORT_KEYS.find(([re]) => re.test(String(name))) || [, "other"])[1];

  // 都道府県 => エリア。venues.json に載っていない会場のために持っておく
  const AREA_BY_PREF = {
    "北海道": "北海道・東北", "青森県": "北海道・東北", "岩手県": "北海道・東北", "宮城県": "北海道・東北",
    "秋田県": "北海道・東北", "山形県": "北海道・東北", "福島県": "北海道・東北",
    "茨城県": "関東", "栃木県": "関東", "群馬県": "関東", "埼玉県": "関東",
    "千葉県": "関東", "東京都": "関東", "神奈川県": "関東",
    "新潟県": "中部", "富山県": "中部", "石川県": "中部", "福井県": "中部", "山梨県": "中部",
    "長野県": "中部", "岐阜県": "中部", "静岡県": "中部", "愛知県": "中部",
    "三重県": "近畿", "滋賀県": "近畿", "京都府": "近畿", "大阪府": "近畿",
    "兵庫県": "近畿", "奈良県": "近畿", "和歌山県": "近畿",
    "鳥取県": "中国・四国", "島根県": "中国・四国", "岡山県": "中国・四国", "広島県": "中国・四国",
    "山口県": "中国・四国", "徳島県": "中国・四国", "香川県": "中国・四国", "愛媛県": "中国・四国", "高知県": "中国・四国",
    "福岡県": "九州・沖縄", "佐賀県": "九州・沖縄", "長崎県": "九州・沖縄", "熊本県": "九州・沖縄",
    "大分県": "九州・沖縄", "宮崎県": "九州・沖縄", "鹿児島県": "九州・沖縄", "沖縄県": "九州・沖縄",
  };

  // venues.json は屋内・屋外を持っていないので、会場名から推測する。
  // 明示された値（matches.json の isIndoor、DB の is_indoor）があればそちらを優先する
  const guessIndoor = (name) => /ドーム|アリーナ|体育館|ARENA|Dome/i.test(String(name));

  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const daysFromToday = (date) => {
    const d = new Date(date);
    d.setHours(0, 0, 0, 0);
    return Math.round((d - today) / 86400000);
  };

  const fmtStart = (g) => {
    const d = g.start;
    const date = `${d.getMonth() + 1}/${d.getDate()}(${WEEK[d.getDay()]})`;
    return g.timeTBD ? `${date} 時刻未定` : `${date} ${d.toTimeString().slice(0, 5)}`;
  };
  // 価格は最安席の「目安」。試合や購入時期で変わるので必ず「目安」と添える
  const priceText = (g) => (g.price == null ? "価格未登録" : `${yen(g.price)}〜（目安）`);

  // -----------------------------------------------------------------
  // 地図の作成
  // -----------------------------------------------------------------
  // 表示範囲を日本周辺（南西：与那国島・波照間島 〜 北東：択捉島）に制限
  const JAPAN_BOUNDS = L.latLngBounds([23.5, 122.0], [46.0, 149.5]);
  // 日本ちょうどに制限すると、日本全体が見えている倍率ではドラッグの余地がなく動かせない。
  // 外側に余白を足して、どの倍率でもドラッグで動かせるようにする。
  const PAN_BOUNDS = JAPAN_BOUNDS.pad(0.15);

  const map = L.map("map", {
    zoomControl: false,
    scrollWheelZoom: false, // ホイール・2本指の操作は下で自前に扱う
    zoomSnap: 0,            // ピンチで少しずつ滑らかに拡大縮小できるようにする（整数に丸めない）
    maxBounds: PAN_BOUNDS,  // この範囲の外へはドラッグできない
    maxBoundsViscosity: 1.0, // 1.0 = 範囲の端でぴたっと止める
  });

  // 日本全体が画面に収まる倍率より小さく縮小できないようにする（画面サイズで変わるので毎回計算）。
  // 最初の表示より前に決めておくこと。あとから決めると、画面が広いときに
  // 倍率の引き上げがアニメーション付きで走り、その完了が
  // 現在地への移動を上書きしてしまう
  const updateMinZoom = () => map.setMinZoom(map.getBoundsZoom(JAPAN_BOUNDS));
  updateMinZoom();

  // 最初の表示は日本全体。広い画面では START_ZOOM より最小倍率のほうが大きくなる
  map.setView([36.5, 137.5], Math.max(START_ZOOM, map.getMinZoom()), { animate: false });

  L.control.zoom({ position: "bottomright" }).addTo(map);

  // Mac のトラックパッドに合わせる：2本指でなぞると移動、ピンチ（＝ctrl+ホイール）で拡大縮小。
  // マウスのホイールも移動になるので、拡大縮小したいときは ⌘ または Ctrl を押しながら回す。
  //
  // ピンチ（＝ctrl+ホイール）は Leaflet 本体と同じやり方で処理する。
  //
  // スマホの 2 本指ピンチ（Leaflet の TouchZoom）は、操作しているあいだ
  // 毎フレーム map._move(center, zoom, { pinch: true }) を呼ぶだけで、
  // タイルは読み直さない。GridLayer が pinch を見て更新を止めるため、
  // 見た目だけが連続的に拡大され、指を離した時点で一度だけ確定する。
  // トラックパッドでも同じ動きにする。
  //
  // 注意：_moveStart / _move / _animateZoom は Leaflet の内部処理で、
  // 公式には非公開（先頭が _）。AGENTS.md で 1.9.4 に固定しているので
  // 採用するが、Leaflet を上げるときはここが動くか必ず確認すること。
  // 使えないときは、溜めてから setZoomAround する従来の方式に落ちる。
  const WHEEL_PX_PER_ZOOM = 60;   // この画素数ぶんのピンチで 1 段階
  const PINCH_END_DELAY = 120;    // これだけ操作が途切れたらピンチ終了とみなす

  const canPinchSmoothly = typeof map._moveStart === "function"
    && typeof map._move === "function"
    && typeof map._animateZoom === "function";

  let pinchZoom = null;    // ピンチ中の行き先の倍率。操作していないときは null
  let pinchCenter = null;
  let pinchPoint = null;   // 指の位置。この点が動かないように拡大する
  let pinchFrame = null;
  let pinchEndTimer = null;

  /** point を動かさずに zoom にするための地図の中心 */
  function zoomAroundCenter(point, zoom) {
    const scale = map.getZoomScale(zoom);
    const viewHalf = map.getSize().divideBy(2);
    const offset = point.subtract(viewHalf).multiplyBy(1 - 1 / scale);
    return map.containerPointToLatLng(viewHalf.add(offset));
  }

  /** 1 フレームぶんの見た目の更新。タイルは読み直さない */
  function pinchStep() {
    pinchFrame = null;
    if (pinchZoom === null || pinchPoint === null) {
      return;
    }
    pinchCenter = zoomAroundCenter(pinchPoint, pinchZoom);
    map._move(pinchCenter, pinchZoom, { pinch: true, round: false });
  }

  /** 指が止まったので確定する。ここで初めてタイルを読み直す */
  function endPinch() {
    pinchEndTimer = null;
    if (pinchZoom === null) {
      return;
    }
    if (pinchFrame !== null) {
      cancelAnimationFrame(pinchFrame);
      pinchFrame = null;
    }

    const zoom = pinchZoom;
    const point = pinchPoint;
    const center = pinchCenter;
    pinchZoom = pinchCenter = pinchPoint = null;

    if (canPinchSmoothly) {
      map._animateZoom(center ?? map.getCenter(), zoom, true, false);
      return;
    }
    map.setZoomAround(point, zoom);
  }

  // Mac のトラックパッドに合わせる：2本指でなぞると移動、ピンチで拡大縮小。
  // マウスのホイールも移動になるので、拡大縮小したいときは ⌘ または Ctrl を押しながら回す。
  map.getContainer().addEventListener("wheel", (e) => {
    e.preventDefault(); // ページ側がスクロールしないようにする
    // 行単位・ページ単位で届くことがあるので、だいたいの画素数にそろえる
    const unit = e.deltaMode === 1 ? 16 : e.deltaMode === 2 ? map.getSize().y : 1;

    if (e.ctrlKey || e.metaKey) { // ピンチ操作はブラウザが ctrl+ホイールとして送ってくる
      if (pinchZoom === null && canPinchSmoothly) {
        map._moveStart(true, false);   // zoomstart / movestart を出す
      }

      // 端を越えた値を溜めこむと戻ってこられなくなるので、その場で止めておく
      const from = pinchZoom ?? map.getZoom();
      pinchZoom = Math.min(
        map.getMaxZoom(),
        Math.max(map.getMinZoom(), from - (e.deltaY * unit) / WHEEL_PX_PER_ZOOM)
      );
      pinchPoint = map.mouseEventToContainerPoint(e);

      if (canPinchSmoothly && pinchFrame === null) {
        pinchFrame = requestAnimationFrame(pinchStep);
      }
      clearTimeout(pinchEndTimer);
      pinchEndTimer = setTimeout(endPinch, PINCH_END_DELAY);
      return;
    }

    map.panBy([e.deltaX * unit, e.deltaY * unit], { animate: false });
  }, { passive: false });

  map.on("resize", updateMinZoom);

  // 背景地図（タイル）。拡大縮小に応じて画像を差し替えるので Google マップのように動く。
  // OpenStreetMap・地理院タイルとも、クレジット表記はライセンス上の義務
  const gsiPale = L.tileLayer("https://cyberjapandata.gsi.go.jp/xyz/pale/{z}/{x}/{y}.png", {
    maxZoom: 18,
    attribution: '<a href="https://maps.gsi.go.jp/development/ichiran.html" target="_blank" rel="noopener">地理院タイル</a>',
  });
  const gsiStd = L.tileLayer("https://cyberjapandata.gsi.go.jp/xyz/std/{z}/{x}/{y}.png", {
    maxZoom: 18,
    attribution: '<a href="https://maps.gsi.go.jp/development/ichiran.html" target="_blank" rel="noopener">地理院タイル</a>',
  });
  const osm = L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors',
  });
  gsiPale.addTo(map);
  L.control.layers(
    { "淡色地図": gsiPale, "標準地図": gsiStd, "OpenStreetMap": osm },
    null,
    { position: "topright" }
  ).addTo(map);

  // 近くのマーカーは縮小時にまとめて表示（プラグインが読めなければ通常表示）。
  // ズーム13以上ではまとめない（スタジアム同士が数百mしか離れていなくても見分けられる）
  const layer = L.markerClusterGroup
    ? L.markerClusterGroup({ showCoverageOnHover: false, maxClusterRadius: 45, disableClusteringAtZoom: 13 })
    : L.layerGroup();
  layer.addTo(map);

  // -----------------------------------------------------------------
  // 試合データの組み立て
  // -----------------------------------------------------------------
  let games = [];
  let visible = [];
  let userLatLng = null;
  let userMarker = null;

  /** data/matches.json の 1 件を、地図で使う形に直す */
  function fromMatch(m, venues, areaById) {
    const venue = venues[m.venue] || null;
    const area = areaById[m.areaId] || null;
    const lat = m.lat ?? venue?.lat;
    const lng = m.lng ?? venue?.lng;
    if (lat == null || lng == null) return null; // 座標のない会場は地図に出せない

    const start = new Date(`${m.date}T${m.startTime || "00:00"}`);
    if (Number.isNaN(start.getTime())) return null;

    const pref = venue?.pref || area?.name || "";
    return {
      sport: sportKey(m.sport),
      sportName: m.sport,
      league: sportOf(sportKey(m.sport)).label,
      title: m.title,
      start,
      timeTBD: !m.startTime,
      price: m.priceMin == null ? null : Number(m.priceMin),
      ticketUrl: m.ticketUrl || "",
      url: "",
      detailUrl: `../match/match-detail.php?id=${encodeURIComponent(m.id)}`,
      v: {
        name: m.venue,
        lat: Number(lat),
        lng: Number(lng),
        pref,
        area: venue?.area || area?.region || AREA_BY_PREF[pref] || "",
        indoor: m.isIndoor != null ? !!m.isIndoor : venue?.indoor ?? guessIndoor(m.venue),
      },
    };
  }

  /** map.php が埋め込んだ大会（v_public_tournaments）を、地図で使う形に直す */
  function fromTournament(t, venues) {
    if (t.lat == null || t.lng == null) return null;
    const start = new Date(String(t.startsAt).replace(" ", "T"));
    if (Number.isNaN(start.getTime())) return null;

    const venue = venues[t.venue] || null;
    const pref = t.pref || venue?.pref || "";
    return {
      sport: sportKey(t.sport),
      sportName: t.sport,
      league: t.organizer ? `主催: ${t.organizer}` : "大会",
      title: t.title,
      start,
      timeTBD: false,
      price: t.fee == null ? null : Number(t.fee),
      ticketUrl: "",
      url: "",
      detailUrl: `../match/match-detail.php?id=${encodeURIComponent(t.id)}`,
      v: {
        name: t.venue,
        lat: Number(t.lat),
        lng: Number(t.lng),
        pref,
        area: venue?.area || AREA_BY_PREF[pref] || "",
        indoor: !!t.isIndoor,
      },
    };
  }

  /** 前日までの試合を落とし、通し番号を振り直す */
  function finalize(list) {
    return list
      .map((g) => ({ ...g, day: daysFromToday(g.start) }))
      .filter((g) => g.day >= 0)
      .map((g, id) => ({ ...g, id }));
  }

  // -----------------------------------------------------------------
  // マーカーとポップアップ
  // -----------------------------------------------------------------
  // 同じ場所の試合はピンを 1 本にまとめ、ポップアップに試合を並べる。
  // 表記違いの会場名でも座標が同じならまとめる（ピンが重なって押せなくなるのを防ぐ）
  const venueMarkers = new Map();
  const placeKey = (g) => `${g.v.lat.toFixed(4)},${g.v.lng.toFixed(4)}`;

  // 「その他」はアイコンだけでは競技が分からないので、競技名を添える
  function leagueText(g) {
    if (g.sport !== "other" || !g.sportName || g.sportName === g.league) return g.league;
    return `${g.sportName} ・ ${g.league}`;
  }

  function makeVenueMarker(venueGames) {
    const first = venueGames[0];
    const s = sportOf(first.sport);
    const count = venueGames.length > 1 ? `<b class="pin-count">${venueGames.length}</b>` : "";
    const icon = L.divIcon({
      className: "pin-icon",
      html: `<div class="pin" style="--c:${s.color}"><span>${s.icon}</span></div>${count}`,
      iconSize: [34, 34],
      iconAnchor: [17, 41],
      popupAnchor: [0, -38],
    });
    const marker = L.marker([first.v.lat, first.v.lng], { icon, title: first.v.name });
    marker.venueGames = venueGames;
    marker.focusId = null;
    marker.on("popupclose", () => { marker.focusId = null; });

    // スマホはポップアップではなく、画面下から出るシートに詳細を出す
    marker.on("click", () => {
      if (MOBILE.matches) openMatchSheet(marker);
    });

    applyMarkerMode(marker);
    return marker;
  }

  /**
   * 画面幅に応じて、ピンの出し方を切り替える。
   * PC はその場のポップアップ、スマホは下から出るシート。
   */
  function applyMarkerMode(marker) {
    if (MOBILE.matches) {
      marker.unbindPopup();
      return;
    }
    if (!marker.getPopup()) {
      marker.bindPopup(() => venuePopupHtml(marker.venueGames, marker.focusId), { maxWidth: 300 });
    }
  }

  function venuePopupHtml(venueGames, focusId) {
    const v = venueGames[0].v;
    const route = `https://www.google.com/maps/dir/?api=1&destination=${v.lat},${v.lng}`;
    // 一覧から選んだ試合を先頭にし、残りは日時の早い順
    const ordered = focusId == null
      ? venueGames
      : [...venueGames.filter((g) => g.id === focusId), ...venueGames.filter((g) => g.id !== focusId)];
    const shown = ordered.slice(0, POPUP_GAME_LIMIT);
    const more = venueGames.length - shown.length;
    const place = [v.pref, v.indoor ? "屋内" : "屋外"].filter(Boolean).join("・");
    const otherNames = [...new Set(venueGames.map((g) => g.v.name))].filter((name) => name !== v.name);

    return `
      <div class="pop">
        <div class="pop-title">${esc(v.name)}</div>
        <div class="pop-sub">${esc(place)} ・ ${venueGames.length}試合</div>
        ${otherNames.length ? `<div class="pop-sub">別の表記: ${esc(otherNames.join(" / "))}</div>` : ""}
        <ul class="pop-games">
          ${shown.map((g) => {
            const s = sportOf(g.sport);
            const detail = [
              g.ticketUrl && `<a href="${esc(g.ticketUrl)}" target="_blank" rel="noopener">公式チケット</a>`,
              g.detailUrl && `<a href="${esc(g.detailUrl)}">試合詳細</a>`,
            ].filter(Boolean).map((link) => ` ・ ${link}`).join("");
            return `
              <li class="${g.id === focusId ? "is-focus" : ""}">
                <span class="tag" style="--c:${s.color}">${s.icon} ${esc(leagueText(g))}</span>
                <strong>${esc(g.title)}</strong>
                <span class="pop-meta">${fmtStart(g)} ・ ${priceText(g)}${detail}</span>
              </li>`;
          }).join("")}
        </ul>
        ${more > 0 ? `<div class="pop-more">ほか ${more}試合（左の一覧で確認できます）</div>` : ""}
        ${shown.some((g) => g.price != null) ? '<div class="pop-note">価格は最安席の目安です。試合や購入時期によって変わります。</div>' : ""}
        <div class="pop-actions">
          <a class="btn" href="${route}" target="_blank" rel="noopener">ルート</a>
        </div>
      </div>`;
  }

  // -----------------------------------------------------------------
  // 絞り込み
  // -----------------------------------------------------------------
  const form = $("#filters");
  // 競技は「何も選んでいない＝すべて表示」。押した競技だけに絞り、追加で押すとその競技も加える
  const chips = $("#sport-chips");

  function buildSportChips(usedKeys) {
    chips.innerHTML =
      '<button type="button" class="chip-all" id="sport-all">すべて表示</button>'
      + Object.entries(SPORTS)
        .filter(([key]) => usedKeys.has(key))
        .map(([key, s]) => `
        <label class="chip" style="--c:${s.color}">
          <input type="checkbox" name="sport" value="${key}">
          <span>${s.icon} ${esc(s.label)}</span>
        </label>`).join("");

    $("#sport-all").addEventListener("click", () => {
      chips.querySelectorAll('input[name="sport"]').forEach((el) => { el.checked = false; });
      syncSportAll();
      render();
    });
    syncSportAll();
  }

  function syncSportAll() {
    const none = !chips.querySelector('input[name="sport"]:checked');
    const all = $("#sport-all");
    if (all) all.setAttribute("aria-pressed", String(none));
  }

  function readFilters() {
    const fd = new FormData(form);
    return {
      sports: new Set(fd.getAll("sport")),
      period: fd.get("period"),
      area: fd.get("area"),
      time: fd.get("time"),
      price: fd.get("price"),
      place: fd.get("place"),
    };
  }

  function matches(g, f) {
    if (f.sports.size > 0 && !f.sports.has(g.sport)) return false;
    if (f.period === "today" && g.day !== 0) return false;
    if (f.period === "tomorrow" && g.day !== 1) return false;
    if (f.period === "week" && g.day > 6) return false;
    if (f.period === "month" && g.day > 30) return false;
    if (f.area !== "all" && g.v.area !== f.area) return false;
    if (f.time !== "all" && g.timeTBD) return false;
    if (f.time === "day" && g.start.getHours() >= 17) return false;
    if (f.time === "night" && g.start.getHours() < 17) return false;
    if (f.price !== "all") {
      const [lo, hi] = PRICE_RANGES[f.price];
      if (g.price == null || g.price < lo || g.price > hi) return false;
    }
    if (f.place === "indoor" && !g.v.indoor) return false;
    if (f.place === "outdoor" && g.v.indoor) return false;
    return true;
  }

  const distanceKm = (g) => (userLatLng ? map.distance(userLatLng, [g.v.lat, g.v.lng]) / 1000 : null);

  // -----------------------------------------------------------------
  // 描画
  // -----------------------------------------------------------------
  /**
   * 絞り込み → 一覧 → 地図の表示範囲 → ピン、の順で更新する。
   *
   * 順番が大事。MarkerCluster は「今の表示範囲」を見てピンを置くので、
   * 地図を動かす前にピンを置くと、動かした先にあるピンが地図に出てこない。
   *
   * @param {"none"|"auto"|"always"} fit 地図を結果に合わせるか
   *   none   … 動かさない（最初の読み込み時。位置の決定は別で行う）
   *   auto   … 結果が 1 件も画面に入っていないときだけ寄せ直す
   *   always … 必ず結果全体に合わせる（エリアを選んだとき）
   */
  function render({ fit = "auto" } = {}) {
    const f = readFilters();
    visible = games.filter((g) => matches(g, f));

    if ($("#sort").value === "distance" && userLatLng) {
      visible.sort((a, b) => distanceKm(a) - distanceKm(b));
    } else {
      visible.sort((a, b) => a.start - b.start);
    }

    const countText = `${visible.length}件の試合`;
    $("#result-count").textContent = countText;
    $("#filter-count").textContent = countText;   // スマホは一覧が無いのでこちらで知らせる
    $("#list").innerHTML = visible.length
      ? visible.map(cardHtml).join("")
      : '<li class="empty">条件に合う試合がありません。<br>条件を変えてみてください。</li>';

    if (fit === "always") {
      fitToResults();
    } else if (fit === "auto") {
      ensureResultsVisible();
    }

    drawMarkers();
  }

  /** いまの絞り込み結果を、会場ごとにまとめてピンにする */
  function drawMarkers() {
    layer.clearLayers();
    venueMarkers.clear();

    const byVenue = new Map();
    [...visible].sort((a, b) => a.start - b.start).forEach((g) => {
      const key = placeKey(g);
      if (!byVenue.has(key)) byVenue.set(key, []);
      byVenue.get(key).push(g);
    });

    byVenue.forEach((venueGames, key) => {
      const marker = makeVenueMarker(venueGames);
      venueMarkers.set(key, marker);
      layer.addLayer(marker);
    });
  }

  function cardHtml(g) {
    const s = sportOf(g.sport);
    const km = distanceKm(g);
    const dist = km == null ? "" : ` ・ ${km < 10 ? km.toFixed(1) : Math.round(km)}km`;
    return `
      <li>
        <button class="card" type="button" data-id="${g.id}" style="--c:${s.color}">
          <span class="card-icon">${s.icon}</span>
          <span class="card-body">
            <span class="card-meta">${fmtStart(g)} ・ ${esc(leagueText(g))}</span>
            <span class="card-title">${esc(g.title)}</span>
            <span class="card-sub">${esc(g.v.name)}${dist}</span>
          </span>
          <span class="card-price">${g.price == null ? "価格未登録" : `${yen(g.price)}〜<small>目安</small>`}</span>
        </button>
      </li>`;
  }

  function fitToResults() {
    if (!visible.length) return;
    const bounds = L.latLngBounds(visible.map((g) => [g.v.lat, g.v.lng]));
    map.fitBounds(bounds, { padding: [40, 40], maxZoom: FOCUS_ZOOM, animate: false });
  }

  /**
   * 現在地を中心に置いたまま、見つかった試合がすべて入る範囲。
   * 現在地をはさんで反対側にも同じだけ広げることで、中心をずらさずに広げる。
   */
  function boundsAroundUser(latlng) {
    const bounds = L.latLngBounds([latlng, latlng]);
    visible.forEach((g) => {
      bounds.extend([g.v.lat, g.v.lng]);
      bounds.extend([2 * latlng.lat - g.v.lat, 2 * latlng.lng - g.v.lng]);
    });
    return bounds;
  }

  /**
   * 絞り込んだ結果が 1 件も画面に入っていなければ、見える位置へ寄せ直す。
   * 絞り込むたびに地図が動くと落ち着かないので、見えているときは動かさない。
   */
  function ensureResultsVisible() {
    if (!visible.length) return;
    const view = map.getBounds();
    const inView = visible.some((g) => view.contains(L.latLng(g.v.lat, g.v.lng)));
    if (!inView) {
      fitToResults();
    }
  }

  // -----------------------------------------------------------------
  // 下から出るシート（スマホ）
  //   絞り込みと試合の詳細は、同じ場所に入れ替わりで出す。
  //   どちらか一方だけが開いている状態を保つ。
  // -----------------------------------------------------------------
  const filterPanel  = $("#filter-panel");
  const filterToggle = $("#filter-toggle");
  const matchSheet   = $("#match-sheet");
  const matchBody    = $("#match-sheet-body");

  function openFilterSheet() {
    closeMatchSheet();
    filterPanel.classList.add("is-open");
    filterToggle.setAttribute("aria-expanded", "true");
    filterPanel.scrollTop = 0;   // 前に開いたときの位置を引きずらない
  }

  function closeFilterSheet() {
    filterPanel.classList.remove("is-open");
    filterToggle.setAttribute("aria-expanded", "false");
  }

  function openMatchSheet(marker) {
    closeFilterSheet();
    matchBody.innerHTML = venuePopupHtml(marker.venueGames, marker.focusId);
    matchSheet.classList.add("is-open");
    matchSheet.setAttribute("aria-hidden", "false");
    matchSheet.scrollTop = 0;
  }

  function closeMatchSheet() {
    matchSheet.classList.remove("is-open");
    matchSheet.setAttribute("aria-hidden", "true");
  }

  function closeSheets() {
    closeFilterSheet();
    closeMatchSheet();
  }

  // 飛んでいる最中に別の試合を選ばれたら、古いほうは打ち切る
  let flyToken = 0;
  let flyFrame = null;

  /**
   * 目的地まで「引きながら移動し、近づいたら寄る」動きで移す。
   *
   * Leaflet の flyTo は引き切ってから動き出すため、前半は同じ場所で
   * 縮んでいるだけに見える。ここでは中心と倍率を別々に動かし、
   * 引いている最中から目的地へ向かうようにしている。
   *
   * _moveStart / _move / _moveEnd は Leaflet の内部処理（ピンチと同じ）。
   * flyTo: true を渡すと、移動中にピンやタイルが作り直されない。
   */
  function flyToPlace(target, targetZoom, onArrive) {
    const start = map.getCenter();
    const zStart = map.getZoom();

    // 出発地と目的地の両方が画面に入る倍率。いちばん引いたときにここまで下げる
    const overview = map.getBoundsZoom(L.latLngBounds([start, target]), false, L.point(80, 80));
    const zMid = Math.max(map.getMinZoom(), Math.min(zStart, targetZoom, overview));
    const dip = Math.max(0, (zStart + targetZoom) / 2 - zMid);

    // 中心の進み方。両端をゆるめ、いちばん引いたあたりで最も速く動かす
    const easeMove = (t) => (1 - Math.cos(Math.PI * t)) / 2;

    const total = FLY_DURATION * 1000;
    const startedAt = performance.now();

    if (flyFrame !== null) cancelAnimationFrame(flyFrame);
    map._moveStart(true, false);

    const step = (now) => {
      const t = Math.min(1, (now - startedAt) / total);
      const p = easeMove(t);

      const center = L.latLng(
        start.lat + (target.lat - start.lat) * p,
        start.lng + (target.lng - start.lng) * p
      );
      // 倍率は山なり。最初から下がり始め、真ん中で zMid、最後に目的の倍率へ戻る
      const zoom = zStart + (targetZoom - zStart) * t - dip * Math.sin(Math.PI * t);

      map._move(center, Math.max(map.getMinZoom(), zoom), { flyTo: true, round: false });

      if (t < 1) {
        flyFrame = requestAnimationFrame(step);
        return;
      }
      flyFrame = null;
      map._move(target, targetZoom, { flyTo: true, round: false });
      map._moveEnd(true);
      onArrive();
    };

    flyFrame = requestAnimationFrame(step);
  }

  /**
   * 一覧で選んだ試合へ地図を移す。
   * いきなり飛ばすと、どこへ移ったのか分からないので、
   * 引きながら向かう動きで、どのあたりの県かを見せる。
   */
  function focusGame(id) {
    const game = games.find((g) => g.id === id);
    if (!game) return;

    if (MOBILE.matches) $("#map").scrollIntoView({ behavior: "smooth", block: "start" });

    const target = L.latLng(game.v.lat, game.v.lng);
    const zoom = Math.max(map.getZoom(), FOCUS_ZOOM);
    const token = ++flyToken;
    let arrived = false;

    /** 着いてから、ピンを置き直して詳細を開く */
    const arrive = () => {
      if (arrived || token !== flyToken) return;
      arrived = true;

      // MarkerCluster は今の表示範囲にあるピンしか置かないので、
      // 着いてから置き直す。ピンは作り直されるのでここで取得すること
      drawMarkers();

      const marker = venueMarkers.get(placeKey(game));
      if (!marker) return;
      marker.focusId = id;   // ポップアップで、選んだ試合を先頭に出すため

      if (MOBILE.matches) {
        openMatchSheet(marker);
        return;
      }
      marker.openPopup();
    };

    // すでにその場所を見ているなら、動かさずにそのまま開く
    if (map.getCenter().distanceTo(target) < 1 && Math.abs(map.getZoom() - zoom) < 0.01) {
      arrive();
      return;
    }

    // 動きが何かで止まっても詳細が開くように、時間で保険をかける
    const fallback = setTimeout(arrive, (FLY_DURATION + 0.6) * 1000);
    const done = () => {
      clearTimeout(fallback);
      arrive();
    };

    if (typeof map._moveStart === "function" && typeof map._move === "function") {
      flyToPlace(target, zoom, done);
      return;
    }
    // 内部処理が使えない場合は Leaflet 本来の flyTo に任せる
    map.once("moveend", done);
    map.flyTo(target, zoom, { duration: FLY_DURATION });
  }

  let statusTimer;
  function showStatus(text) {
    const el = $("#status");
    el.textContent = text;
    el.hidden = false;
    clearTimeout(statusTimer);
    statusTimer = setTimeout(() => { el.hidden = true; }, 3000);
  }

  // 最初の表示だけは、アニメーションなしでいきなり現在地にする
  let locateInstant = false;

  function locate(instant = false) {
    showStatus("現在地を取得中…");
    locateInstant = instant;
    // 寄せ方は locationfound 側で決めるので、ここでは位置を取るだけにする
    map.locate({ setView: false });
  }

  /**
   * 最初の表示を現在地にする。
   * すでに位置情報が許可されているときだけ行い、開いた途端に
   * 許可を求めるダイアログを出すことはしない（許可していない人には日本全体を見せる）。
   */
  function locateOnStartIfAllowed() {
    if (!navigator.permissions?.query) {
      return;   // 対応していないブラウザでは何もしない
    }
    navigator.permissions
      .query({ name: "geolocation" })
      .then((status) => {
        if (status.state === "granted") {
          locate(true);   // 開いた直後なので、動かさずにいきなり現在地を出す
        }
      })
      .catch(() => {
        // 問い合わせに失敗しても、日本全体の表示のままで問題ない
      });
  }

  // -----------------------------------------------------------------
  // イベント
  // -----------------------------------------------------------------
  form.addEventListener("change", (e) => {
    if (e.target.name === "sport") syncSportAll();
    // エリアを選んだときは必ずそこへ。ほかの条件は、結果が画面外のときだけ寄せ直す
    render({ fit: e.target.name === "area" ? "always" : "auto" });
  });
  form.addEventListener("reset", () => setTimeout(() => {
    syncSportAll();
    render({ fit: "always" });   // 条件を戻したので、全件が見える位置に合わせ直す
  }, 0));

  $("#list").addEventListener("click", (e) => {
    const card = e.target.closest(".card");
    if (!card) return;
    // スマホは一覧が全画面なので、選んだら閉じて地図に戻る
    if (MOBILE.matches) closeFilterSheet();
    focusGame(Number(card.dataset.id));
  });

  $("#sort").addEventListener("change", () => {
    if ($("#sort").value === "distance" && !userLatLng) locate();
    render();
  });

  $("#fit").addEventListener("click", () => { closeSheets(); fitToResults(); });
  $("#locate").addEventListener("click", () => { closeSheets(); locate(); });

  // ハンバーガーで絞り込みを開閉する（スマホ）
  filterToggle.addEventListener("click", () => {
    if (filterPanel.classList.contains("is-open")) {
      closeFilterSheet();
    } else {
      openFilterSheet();
    }
  });

  $("#filter-close").addEventListener("click", closeFilterSheet);
  $("#match-sheet-close").addEventListener("click", closeMatchSheet);

  // 地図を触ったらシートを閉じる。
  // ピンや Leaflet のボタン（ズーム・地図の種類）は map の click まで
  // イベントを通さないので、地図コンテナでキャプチャして拾う。
  // ピンの場合はこのあとマーカー側の処理が走り、詳細シートが開く
  map.getContainer().addEventListener("click", closeSheets, true);

  // Esc でも閉じられるようにする
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") closeSheets();
  });


  map.on("locationfound", (e) => {
    userLatLng = e.latlng;
    if (locateInstant) {
      // 開いた直後。現在地を中心に置いたまま、試合が画面に入る広さにする
      // （現在地だけに寄せると、近くに無い競技が画面の外に出てしまう）
      map.fitBounds(boundsAroundUser(e.latlng), {
        padding: [40, 40],
        maxZoom: LOCATE_ZOOM,
        animate: false,
      });
    } else {
      map.setView(e.latlng, LOCATE_ZOOM);
    }
    locateInstant = false;
    drawMarkers();   // 動かしたあとの表示範囲で置き直す
    if (!userMarker) {
      userMarker = L.circleMarker(e.latlng, { radius: 8, color: "#fff", weight: 3, fillColor: "#0b6bcb", fillOpacity: 1 })
        .bindTooltip("現在地")
        .addTo(map);
    } else {
      userMarker.setLatLng(e.latlng);
    }
    showStatus("現在地を表示しました");
    render();
  });

  map.on("locationerror", () => {
    showStatus("現在地を取得できませんでした（位置情報の許可を確認してください）");
    $("#sort").value = "date";
    render();
  });

  // 画面サイズが変わったら、地図のサイズとピンの出し方をそろえ直す
  MOBILE.addEventListener("change", () => {
    map.invalidateSize();
    closeSheets();
    venueMarkers.forEach(applyMarkerMode);
  });

  // -----------------------------------------------------------------
  // 読み込み
  // -----------------------------------------------------------------
  /** map.php が <script type="application/json"> で埋め込んだ大会を読む */
  function readEmbeddedTournaments() {
    const el = document.getElementById("map-tournaments");
    if (!el) return [];
    try {
      const parsed = JSON.parse(el.textContent);
      return Array.isArray(parsed) ? parsed : [];
    } catch {
      return []; // 埋め込みが壊れていても、仮データだけで地図は動く
    }
  }

  const loadJson = (path) =>
    fetch(path).then((res) => {
      if (!res.ok) throw new Error(`${path}: ${res.status}`);
      return res.json();
    });

  (async () => {
    let venues = {};
    let matchList = [];
    let areas = [];

    try {
      [venues, matchList, areas] = await Promise.all([
        loadJson("../../data/venues.json"),
        loadJson("../../data/matches.json"),
        loadJson("../../data/areas.json"),
      ]);
    } catch (err) {
      console.error("[SPOTIVE] 地図データを読み込めませんでした", err);
      showStatus("試合データを読み込めませんでした");
    }

    const areaById = Object.fromEntries(areas.map((a) => [a.id, a]));

    const fromMatches = matchList.map((m) => fromMatch(m, venues, areaById)).filter(Boolean);
    const fromTournaments = readEmbeddedTournaments().map((t) => fromTournament(t, venues)).filter(Boolean);
    games = finalize([...fromMatches, ...fromTournaments]);

    buildSportChips(new Set(games.map((g) => g.sport)));
    render({ fit: "none" });

    // 位置情報が許可済みなら、最初から自分のまわりを見せる
    locateOnStartIfAllowed();
  })();
});
