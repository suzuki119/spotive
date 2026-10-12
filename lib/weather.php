<?php

/**
 * lib/weather.php
 * Open-Meteo の天気予報 API で、試合の日の会場の天気を取る（試作）。
 * 企画書の「天気など、スポーツ観戦に必要な情報もまとめて提供する」のデモ。
 *
 * Open-Meteo は API キーが要らないが、ほかの外部 API と同じく PHP から呼ぶ（JS から直接呼ばない）。
 * 無料で使えるのは非商用の利用だけ。商用にするときは有料プランに切り替える。
 * データは CC BY 4.0 なので、表示する画面に「Open-Meteo」のクレジットを出すこと。
 *
 * 予報が出るのは今日から 15 日後まで。それより先の試合は呼ばない（呼んでもエラーになる）。
 * 同じ会場・同じ日を何度も開いても呼びすぎないよう、結果は 1 時間だけ一時ファイルに取っておく。
 */

declare(strict_types=1);

const WEATHER_FORECAST_DAYS = 15;       // 今日から何日後まで予報を取るか（Open-Meteo の上限）
const WEATHER_CACHE_TTL     = 60 * 60;  // 1 時間

/** WMO の天気コード => [表示名, アイコン] */
const WEATHER_CODES = [
  0  => ['快晴', '☀️'],
  1  => ['晴れ', '🌤️'],
  2  => ['晴れ時々くもり', '⛅'],
  3  => ['くもり', '☁️'],
  45 => ['霧', '🌫️'],
  48 => ['霧', '🌫️'],
  51 => ['霧雨', '🌦️'],
  53 => ['霧雨', '🌦️'],
  55 => ['霧雨', '🌦️'],
  56 => ['霧雨', '🌦️'],
  57 => ['霧雨', '🌦️'],
  61 => ['雨', '🌧️'],
  63 => ['雨', '🌧️'],
  65 => ['強い雨', '🌧️'],
  66 => ['雨', '🌧️'],
  67 => ['強い雨', '🌧️'],
  71 => ['雪', '🌨️'],
  73 => ['雪', '🌨️'],
  75 => ['大雪', '🌨️'],
  77 => ['雪', '🌨️'],
  80 => ['にわか雨', '🌦️'],
  81 => ['にわか雨', '🌦️'],
  82 => ['強いにわか雨', '🌧️'],
  85 => ['にわか雪', '🌨️'],
  86 => ['にわか雪', '🌨️'],
  95 => ['雷雨', '⛈️'],
  96 => ['雷雨', '⛈️'],
  99 => ['雷雨', '⛈️'],
];

/** @return array{label:string,icon:string} 知らないコードは「—」 */
function weather_code_info(int $code): array
{
  [$label, $icon] = WEATHER_CODES[$code] ?? ['—', '🌡️'];
  return ['label' => $label, 'icon' => $icon];
}

/** その日の予報がもう出ているか（今日〜15 日後） */
function weather_in_range(DateTimeImmutable $date): bool
{
  $today = new DateTimeImmutable('today', $date->getTimezone());
  $day   = $date->setTime(0, 0);
  return $day >= $today && $day <= $today->modify('+' . WEATHER_FORECAST_DAYS . ' days');
}

/**
 * 会場の、その日の天気。
 * $hour を渡すと、その時刻（試合開始）の気温・降水確率・天気も付ける。
 *
 * @return array{
 *   date:string, code:int, max:float, min:float, rain:int,
 *   at:?array{hour:int, code:int, temp:float, rain:int}
 * }
 * @throws InvalidArgumentException 日本の外の座標、予報の出ていない日
 * @throws RuntimeException API を呼べなかったとき（詳細は error_log に出す）
 */
function weather_forecast(float $lat, float $lng, DateTimeImmutable $date, ?int $hour = null): array
{
  // おかしな座標ではリクエストしない（AGENTS.md「外部 API」）
  if ($lat < 20 || $lat > 46 || $lng < 122 || $lng > 154) {
    throw new InvalidArgumentException('Coordinates are outside Japan');
  }
  if (!weather_in_range($date)) {
    throw new InvalidArgumentException('No forecast for this date yet');
  }

  // 約 1km 単位に丸める。予報の格子はそれより粗いので、結果は変わらない
  $lat = round($lat, 2);
  $lng = round($lng, 2);
  $day = $date->format('Y-m-d');

  $cacheDir  = sys_get_temp_dir() . '/spotive-weather';
  $cacheFile = $cacheDir . '/' . sha1($lat . ',' . $lng . ',' . $day) . '.json';
  $data      = null;
  if (is_file($cacheFile) && time() - (int) filemtime($cacheFile) < WEATHER_CACHE_TTL) {
    $data = json_decode((string) file_get_contents($cacheFile), true);
  }

  if (!is_array($data)) {
    $ch = curl_init('https://api.open-meteo.com/v1/forecast?' . http_build_query([
      'latitude'   => $lat,
      'longitude'  => $lng,
      'daily'      => 'weather_code,temperature_2m_max,temperature_2m_min,precipitation_probability_max',
      'hourly'     => 'weather_code,temperature_2m,precipitation_probability',
      'timezone'   => 'Asia/Tokyo',
      'start_date' => $day,
      'end_date'   => $day,
    ]));
    // 詳細画面の表示を待たせすぎないよう、短めに切る
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT        => 4,
      CURLOPT_CONNECTTIMEOUT => 2,
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    // curl_close() は PHP 8 から何もせず、8.5 では非推奨なので呼ばない（lib/geoapify.php と同じ）

    $data = is_string($body) ? json_decode($body, true) : null;
    if ($code !== 200 || !is_array($data) || !isset($data['daily']['time'][0])) {
      error_log(sprintf('[SPOTIVE] Open-Meteo error: status=%d', $code));
      throw new RuntimeException('Open-Meteo request failed');
    }

    if (is_dir($cacheDir) || @mkdir($cacheDir, 0700, true)) {
      @file_put_contents($cacheFile, json_encode($data));
    }
  }

  $daily  = $data['daily'];
  $result = [
    'date' => $day,
    'code' => (int) ($daily['weather_code'][0] ?? -1),
    'max'  => (float) ($daily['temperature_2m_max'][0] ?? 0),
    'min'  => (float) ($daily['temperature_2m_min'][0] ?? 0),
    'rain' => (int) ($daily['precipitation_probability_max'][0] ?? 0),
    'at'   => null,
  ];

  // hourly は 0 時〜23 時の 24 件。試合開始の時刻の分だけ取り出す
  $hourly = $data['hourly'] ?? [];
  if ($hour !== null && isset($hourly['temperature_2m'][$hour])) {
    $result['at'] = [
      'hour' => $hour,
      'code' => (int) ($hourly['weather_code'][$hour] ?? -1),
      'temp' => (float) $hourly['temperature_2m'][$hour],
      'rain' => (int) ($hourly['precipitation_probability'][$hour] ?? 0),
    ];
  }

  return $result;
}
