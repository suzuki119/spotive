<?php

/**
 * lib/icon.php
 * images/icons/ の SVG アイコンを、文字と同じように使うための部品。
 *
 * <img> ではなく CSS のマスク（sass/style.scss の .icon）で描くので、
 * アイコンの色は周りの文字の色（color）に、大きさは文字の大きさ（font-size）に合わせて変わる。
 * 絵文字は端末ごとに見た目が違うので、画面に出すアイコンはこれを使う。
 */

declare(strict_types=1);

require_once __DIR__ . '/support.php';

/**
 * アイコンの <span>。飾りなので読み上げない（意味がある場合は、そばに文字を置く）。
 * $name は images/icons/ のファイル名から .svg を除いたもの（例：'sport-soccer'）。
 */
function icon_html(string $name, string $class = ''): string
{
  $src = url('images/icons/' . $name . '.svg');
  return '<span class="icon' . ($class === '' ? '' : ' ' . h($class)) . '"'
    . ' style="--icon: url(&quot;' . h($src) . '&quot;)" aria-hidden="true"></span>';
}
