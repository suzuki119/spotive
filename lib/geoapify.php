<?php

/**
 * lib/geoapify.php
 * Geoapify Places API で、会場の周辺施設（飲食店・カフェ・ホテル・駐車場・コンビニ）を探す。
 * 企画書の機能 6「周辺施設表示」のデモ。DB 設計を含めた本実装は、チームで決めてから行う。
 *
 * API キーはブラウザに見せないため、必ずこの PHP から呼ぶ（JS から直接呼ばない）。
 * キーは config/geoapify.local.php に置く（config/geoapify.example.php を参照）。
 *
 * 無料プランは 1 日 3,000 クレジット。1 会場の検索で 5 クレジット（種類ごとに 1 回）使う。
 * 同じ会場を何度も開いても使い切らないよう、結果はしばらく一時ファイルに取っておく。
 */

declare(strict_types=1);

/** 施設の種類 => Geoapify のカテゴリ。地図のアイコンと並び順もこの順 */
const NEARBY_KINDS = [
  'food'        => ['catering.restaurant', 'catering.fast_food'],
  'cafe'        => ['catering.cafe'],
  'convenience' => ['commercial.convenience'],
  'hotel'       => ['accommodation.hotel', 'accommodation.guest_house', 'accommodation.hostel'],
  'parking'     => ['parking.cars'],
];

const NEARBY_RADIUS_M       = 800;      // 会場から歩いて 10 分ほど
const NEARBY_LIMIT_PER_KIND = 8;        // 20 件までは 1 回 1 クレジット
const NEARBY_CACHE_TTL      = 60 * 60;  // 1 時間

/** API キー。置かれていなければ空文字 */
function geoapify_key(): string
{
  $file = __DIR__ . '/../config/geoapify.local.php';
  $conf = is_file($file) ? (array) require $file : [];
  return trim((string) ($conf['api_key'] ?? ''));
}

/**
 * 会場の周辺施設を、近い順に返す。
 *
 * @return list<array{name:string,kind:string,lat:float,lng:float,distance:int}>
 * @throws RuntimeException API を呼べなかったとき（詳細は error_log に出す）
 */
function geoapify_nearby(float $lat, float $lng): array
{
  // 座標を丸めて、ほぼ同じ場所なら同じ結果を使い回す（約 10m 単位）
  $lat = round($lat, 4);
  $lng = round($lng, 4);

  $cacheDir  = sys_get_temp_dir() . '/spotive-geoapify';
  $cacheFile = $cacheDir . '/' . sha1($lat . ',' . $lng) . '.json';
  if (is_file($cacheFile) && time() - (int) filemtime($cacheFile) < NEARBY_CACHE_TTL) {
    $cached = json_decode((string) file_get_contents($cacheFile), true);
    if (is_array($cached)) {
      return $cached;
    }
  }

  $key = geoapify_key();
  if ($key === '') {
    throw new RuntimeException('Geoapify API key is not set');
  }

  // 近い順に全部取ると飲食店ばかりになるので、種類ごとに数件ずつ取る（並行して問い合わせる）
  $multi   = curl_multi_init();
  $handles = [];
  foreach (NEARBY_KINDS as $kind => $categories) {
    $ch = curl_init('https://api.geoapify.com/v2/places?' . http_build_query([
      'categories' => implode(',', $categories),
      'filter'     => sprintf('circle:%F,%F,%d', $lng, $lat, NEARBY_RADIUS_M),
      'bias'       => sprintf('proximity:%F,%F', $lng, $lat),
      'limit'      => NEARBY_LIMIT_PER_KIND,
      'lang'       => 'ja',
      'apiKey'     => $key,
    ]));
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT        => 8,
      CURLOPT_CONNECTTIMEOUT => 4,
    ]);
    curl_multi_add_handle($multi, $ch);
    $handles[$kind] = $ch;
  }

  do {
    $status = curl_multi_exec($multi, $running);
    if ($running) {
      curl_multi_select($multi);
    }
  } while ($running && $status === CURLM_OK);

  $places = [];
  $failed = 0;
  foreach ($handles as $kind => $ch) {
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $data = json_decode((string) curl_multi_getcontent($ch), true);
    curl_multi_remove_handle($multi, $ch);

    if ($code !== 200 || !is_array($data)) {
      // URL にはキーが入っているので、ログには出さない
      error_log(sprintf('[SPOTIVE] Geoapify error: kind=%s status=%d', $kind, $code));
      $failed++;
      continue;
    }

    foreach ($data['features'] ?? [] as $feature) {
      $p    = $feature['properties'] ?? [];
      $name = trim((string) ($p['name'] ?? ''));
      if ($name === '') {
        continue;   // 名前の無い駐車場などは、地図に出しても何か分からない
      }
      $places[] = [
        'name'     => $name,
        'kind'     => $kind,
        'lat'      => (float) ($p['lat'] ?? 0),
        'lng'      => (float) ($p['lon'] ?? 0),
        'distance' => (int) ($p['distance'] ?? 0),
      ];
    }
  }
  curl_multi_close($multi);

  if ($failed === count($handles)) {
    throw new RuntimeException('Geoapify request failed');
  }

  usort($places, static fn(array $a, array $b): int => $a['distance'] <=> $b['distance']);

  // 一部の種類だけ失敗したときは、次に開いたときに取り直せるよう保存しない
  if ($failed === 0 && (is_dir($cacheDir) || @mkdir($cacheDir, 0700, true))) {
    @file_put_contents($cacheFile, json_encode($places, JSON_UNESCAPED_UNICODE));
  }

  return $places;
}
