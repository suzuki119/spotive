/**
 * gsi-tiles.js
 * 地理院タイル（淡色地図・標準地図）の Leaflet レイヤーを作る。
 * 地図（js/pages/map.js）と遠征プランの地図（js/pages/travel.js）から使う。
 *
 * クレジット表記はライセンス上の義務。
 * ズーム 5〜8 のタイルは小縮尺地図（500万分1）で、「地理院タイル」に加えて
 * 海岸線などの元データの出典も出すことが条件になっている。
 * そのズームのときだけ、その出典を足す（拡大したら外す）。
 * 出典の文言は https://maps.gsi.go.jp/development/ichiran.html のとおり。変えないこと。
 *
 * ページをまたいで使うため、SpotiveGsiTiles だけをグローバルに置く。
 */

const SpotiveGsiTiles = (() => {
  const CREDIT = '<a href="https://maps.gsi.go.jp/development/ichiran.html" target="_blank" rel="noopener">地理院タイル</a>';

  const VMAP0 = 'Shoreline data is derived from: United States. National Imagery and Mapping Agency. "Vector Map Level 0 (VMAP0)." Bethesda, MD: Denver, CO: The Agency; USGS Information Services, 1997.';
  const GEBCO = 'The bathymetric contours are derived from those contained within the GEBCO Digital Atlas, published by the BODC on behalf of IOC and IHO (2003) (<a href="https://www.gebco.net" target="_blank" rel="noopener">https://www.gebco.net</a>) 海上保安庁許可第292502号（水路業務法第25条に基づく類似刊行物）';

  /** 地図の種類 => タイルの名前と、小縮尺（ズーム 5〜8）のときに足す出典 */
  const KINDS = {
    pale: { path: "pale", smallScale: VMAP0 },
    std: { path: "std", smallScale: `${GEBCO} / ${VMAP0}` },
  };
  const SMALL_SCALE_MIN = 5;
  const SMALL_SCALE_MAX = 8;

  /** kind は "pale"（淡色地図）か "std"（標準地図） */
  function layer(kind, options = {}) {
    const def = KINDS[kind];
    const tile = L.tileLayer(`https://cyberjapandata.gsi.go.jp/xyz/${def.path}/{z}/{x}/{y}.png`, {
      maxZoom: 18,
      attribution: CREDIT,
      ...options,
    });

    // 小縮尺の出典は、このレイヤーが地図に出ていて、ズームが 5〜8 のときだけ出す
    // （"remove" の時点では tile._map が消えているので、載せた地図を自分で覚えておく）
    let map = null;
    let shown = false;
    const setShown = (need) => {
      const control = map && map.attributionControl;
      if (!control || need === shown) return;
      if (need) control.addAttribution(def.smallScale);
      else control.removeAttribution(def.smallScale);
      shown = need;
    };
    const update = () => {
      const zoom = map.getZoom();
      setShown(zoom >= SMALL_SCALE_MIN && zoom <= SMALL_SCALE_MAX);
    };

    tile.on("add", (e) => {
      map = e.target._map;
      map.on("zoomend", update);
      update();
    });
    tile.on("remove", () => {
      if (!map) return;
      map.off("zoomend", update);
      setShown(false);
      map = null;
    });
    return tile;
  }

  return { layer };
})();
