# AGENTS.md — SPOTIVE 制作ルール

このファイルは SPOTIVE をグループで制作するための共通ルールです。
人間のメンバーも AI エージェントも、作業前に必ずこのファイルを読んでください。
判断に迷ったら、ここに書かれているルールを優先します。ここに書かれていないことは、勝手に決めずにチームに確認してください。

---

## 1. プロジェクト概要

**チーム名：ファイト・クラブ**
**企画書：`project/企画書.png`（2026.09.18）**

このセクションは企画書の内容をもとにしています。**企画書が更新されたら、ここも一緒に更新してください。**

### 最終目標

> **「スポーツを、もっと身近な日常にする」**

スポーツを身近なものにし、観戦をきっかけとして運動習慣や健康づくりを促進するとともに、地域の活性化を目指します。

### アプリコンセプト

> **「スポーツ観戦をもっと身近に、もっと楽しく」**

全国のスポーツイベントを地図上で探し、自分に合った試合を簡単に見つけられる**スポーツ観戦支援アプリ**です。

想定しているユーザーの声は次の3つです。**迷ったときは、この3つに答えられているかで判断してください。**

- 「今日は近くで、何か試合あるかな？」
- 「遠征先でもう1試合観られないかな？」
- 「学生だから3,000円以内で観たい」

野球・サッカー・バスケットボール・バレーボールなど複数のスポーツの試合情報をまとめ、開催地域・日時・競技・料金などの条件から、自分に合った観戦を探せるようにします。
試合情報だけでなく、会場へのアクセス、周辺施設、ホテル、天気など、スポーツ観戦に必要な情報もまとめて提供します。

### 何を作るのかの軸

**SPOTIVE の本体は「観戦者」のためのアプリです。** 試合を探して、観に行くまでを助けるのが主役です。

主催者アカウント（個人・団体・チーム・学校・企業・スポーツ協会や連盟が試合やイベントを掲載できる仕組み）も用意しますが、これは**観戦できる試合を増やすための裏側の仕組み**という位置づけです。`db/schema.sql` に主催者認証や大会確認のテーブルが多いのはそのためで、アプリの主役が主催者に移ったわけではありません。

**画面を作るときは、常に観戦者の視点を優先してください。**

### ターゲット

- スポーツ観戦が好きな人
- 遠征をよくするファン
- 複数競技を観戦する人
- 初めてスポーツ観戦をする人
- 学生・家族連れ

解決したい課題：

- 「近くで観戦できる試合を探したい」
- 「遠征先でも別の試合を観戦したい」
- 「予算内で観戦できる試合を探したい」

### 企画の根拠（出典：スポーツ庁）

発表やレビューで「なぜこれを作るのか」を聞かれたときの根拠です。

**現地観戦率**

- スポーツを「見る」人は **68.7%**
- そのうち現地観戦をする人は **25.9%**（全国の **4人に1人**）

**スポーツツーリズム関連消費（億円）**

| 年度 | 消費額 |
| --- | --- |
| 令和4年度 | 1,627 |
| 令和5年度 | 2,203 |
| 令和6年度 | 2,645 |
| 令和7年度 | 3,800 |

「観るだけの人」と「現地に行く人」の間に差があり、そこを埋めることに市場の伸びもある、というのが SPOTIVE の出発点です。

### 主要機能と優先順位

企画書に挙げた6つの機能です。**1〜4 を先に作ります。5・6 は後回しにします。**

| # | 機能 | 内容 | 今回の扱い |
| --- | --- | --- | --- |
| 1 | 試合マップ | 全国の試合会場を地図上に表示。プロ野球・Bリーグ・Jリーグ等、競技ごとにアイコンや色を分ける | **先に作る** |
| 2 | スケジュール一覧 | 日程ごとに試合を一覧表示。開始時間・会場・チケット価格などを確認できる | **先に作る** |
| 3 | 条件検索・フィルター | チケット価格・エリア・日付などで絞り込む | **先に作る** |
| 4 | お気に入り登録 | お気に入りのチームを登録できる | **先に作る** |
| 5 | 遠征サポート | 遠征先の周辺試合を提案。「土曜に野球観戦、日曜にサッカー観戦」など、旅行と観戦を組み合わせた提案 | **後回し**（DB 設計を含め後で決める） |
| 6 | 周辺施設表示 | 会場周辺の飲食店・カフェ・ホテル・駐車場・コンビニを表示し、観戦前後も楽しめるようにする | **後回し**（DB 設計を含め後で決める） |

**5・6 は「やらない」ではなく「今は手をつけない」です。** データベースの設計も含めて後で決めるので、今の段階で先回りしてテーブルやページを作らないでください。まず 1〜4 を動く状態にします。

### 収益モデル

- チケット販売手数料
- サブスクリプション
- 広告収入
- 周辺施設の紹介料・送客収入

実装するものではありませんが、**画面を考えるときの前提**になります（例：周辺施設の表示は送客につながる導線なので、ただの飾りにしない）。

### 対応デバイス

**モバイルファーストで設計します。**

- スマートフォン版を基準として作り、PC 表示でも基本的なレイアウト・UI 構成を維持する
- PC 専用の大幅なレイアウト変更は行わない
- 画面幅に応じて、余白・コンテンツ幅などを調整する

CSS は「スマホ幅のスタイルを先に書き、`min-width` のメディアクエリで広い画面に広げる」書き方で統一します。`max-width` で縮めていく書き方はしません。

```scss
// ○ こう書く
.match-card {
  padding: 16px;

  @media (min-width: 768px) {
    padding: 24px;
  }
}

// × こうは書かない
.match-card {
  padding: 24px;

  @media (max-width: 767px) {
    padding: 16px;
  }
}
```

---

## 2. デザインルール

### カラー

スポーツと地図に合わせたブルー系です。値は `scss/_variables.scss` にまとめ、**SCSS の中に直接カラーコードを書かないでください。**

| 役割 | 変数名 | カラーコード | 用途 |
| --- | --- | --- | --- |
| メイン | `$color-primary` | `#0B63E5` | ヘッダー、リンク、選択中の状態、地図のピン |
| メイン濃 | `$color-primary-dark` | `#0847A6` | ホバー、押下時 |
| メイン淡 | `$color-primary-light` | `#E7F0FE` | 選択中の背景、淡い塗り |
| サブ | `$color-secondary` | `#0F172A` | 見出し文字、フッター背景 |
| アクセント | `$color-accent` | `#FF6B2C` | CTA ボタン、強調バッジ |
| 本文 | `$color-text` | `#1F2937` | 本文テキスト |
| 補助文字 | `$color-text-sub` | `#6B7280` | 日時、補足情報 |
| 枠線 | `$color-border` | `#E5E7EB` | カードの枠、区切り線 |
| 背景 | `$color-bg` | `#FFFFFF` | ページ背景 |
| 背景（淡） | `$color-bg-gray` | `#F5F7FA` | セクション背景、地図の下地 |

使い分けのルール：

- **アクセントのオレンジは CTA と強調だけに使う。** 使いすぎると「押してほしい場所」が分からなくなります
- 地図の上に置く要素は、背景が淡いグレー／ベージュになるため、必ず `$color-primary` か `$color-bg`（白）で塗って埋もれないようにする
- エラー表示は赤（`#DC2626`）を使い、アクセントのオレンジとは区別する

### フォント

**Futura は無償の Web フォントが存在せず和文も持たないため、Web 上では Futura 系の無償フォント `Jost` で代替します。**

| 用途 | フォント | ウェイト |
| --- | --- | --- |
| 見出し（英数字） | `Jost` | 600 |
| 見出し（和文） | `Noto Sans JP` | 700 |
| 本文 | `Noto Sans JP` | 400 |
| 数字の強調（料金・日時） | `Jost` | 600 |

読み込みは Google Fonts を使い、全ページ共通で同じタグを貼ります。

```html
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link
  href="https://fonts.googleapis.com/css2?family=Jost:wght@400;600&family=Noto+Sans+JP:wght@400;500;700&display=swap"
  rel="stylesheet"
/>
```

`font-family` は Jost を先に書きます。こうすると英数字は Jost、和文は Jost に無いので自動的に Noto Sans JP が使われます。

```scss
$font-heading: "Jost", "Noto Sans JP", sans-serif;
$font-body: "Noto Sans JP", sans-serif;
```

ロゴやキービジュアルなど**画像として書き出すもの**には、本物の Futura を使って構いません。HTML のテキストとして表示する部分だけ Jost に統一します。

### 余白

**8px グリッド**を基準にします。余白の値は次の中から選び、それ以外の半端な数値（13px、22px など）は使いません。

```
4px / 8px / 12px / 16px / 24px / 32px / 48px / 64px / 80px
```

目安：

- 要素の内側（padding）… 16px（スマホ）／ 24px（PC）
- カード同士の間隔 … 16px
- セクション同士の間隔 … 48px（スマホ）／ 80px（PC）
- 画面の左右の余白 … 16px（スマホ）／ 24px（タブレット以上）

### 角丸

| 対象 | 値 | 変数名 |
| --- | --- | --- |
| カード、画像、モーダル | 16px | `$radius-lg` |
| ボタン、入力欄、タグ | 8px | `$radius-md` |
| バッジ、ピル、アバター | 999px | `$radius-full` |

### ブレイクポイント

ブレイクポイントは**2 つだけ**です。増やさないでください。

| 名前 | 値 | 想定 |
| --- | --- | --- |
| タブレット | `min-width: 768px` | iPad 縦など |
| PC | `min-width: 1024px` | ノート PC 以上 |

- コンテンツの最大幅は **1200px**、中央寄せ
- 変えるのは原則「余白」と「コンテンツ幅」、必要に応じて「カラム数」まで。レイアウトの構造そのものは変えません

---

## 3. 技術構成

| 項目 | 使うもの |
| --- | --- |
| マークアップ | 素の HTML5 ＋ **PHP 8**（テンプレートエンジンやフレームワークは使わない） |
| サーバーサイド | **PHP 8.0 以上**（Laravel などのフレームワークは使わない） |
| データベース | **MySQL 8.0**（`db/schema.sql` が正） |
| 実行環境 | **XAMPP**（Apache ＋ MySQL）。`http://localhost/spotive/` で開く |
| CSS | **SCSS**（Live Sass Compiler でコンパイル） |
| JavaScript | **素の JavaScript**（フレームワークなし） |
| 地図 | **Leaflet**（CDN 読み込み、OpenStreetMap、API キー不要） |
| ビルドツール | なし（npm / Vite / webpack / Composer は使わない） |

### SCSS のコンパイル

VSCode 拡張 **Live Sass Compiler** を使います。**全員が同じ設定を使ってください。** 設定が違うと、中身は同じなのに `style.css` の差分が全行変更になり、毎回コンフリクトします。

リポジトリ直下の `.vscode/settings.json` に設定を置いてあるので、VSCode でこのフォルダを開けば自動的に適用されます。個人の設定（ユーザー設定）で上書きしないでください。

```json
{
  "liveSassCompile.settings.formats": [
    {
      "format": "expanded",
      "extensionName": ".css",
      "savePath": "/css"
    }
  ],
  "liveSassCompile.settings.generateMap": false,
  "liveSassCompile.settings.autoprefix": ["> 1%", "last 2 versions"]
}
```

コンパイルされるのは `scss/style.scss` → `css/style.css` の 1 本だけです。

### SCSS のファイル構成

```
scss/
├── style.scss          ← ここで全パーシャルを @use するだけ。スタイルは書かない
├── _variables.scss     ← 色・フォント・余白・角丸・BP の変数
├── _mixin.scss         ← メディアクエリなどの mixin
├── _reset.scss         ← リセット CSS
├── _base.scss          ← body, a, img などの基本スタイル
├── _header.scss        ← 共通ヘッダー
├── _footer.scss        ← 共通フッター
├── _button.scss        ← 共通ボタン
├── _match-card.scss    ← 共通パーツ（試合カードなど）
├── _sports-map.scss    ← ページ固有（sports-map.html 用）
├── _event-detail.scss  ← ページ固有（event-detail.html 用）
└── ...
```

**担当ページのスタイルは、必ず自分のページ用のパーシャルに書いてください。** 共通パーツ（`_header.scss` など）を触るときは、他のページが崩れる可能性があるので、事前にチームに共有してください。

`style.scss` の中身はこの形だけです。

```scss
@use "variables" as *;
@use "mixin" as *;
@use "reset";
@use "base";
@use "header";
@use "footer";
@use "button";
@use "match-card";
@use "sports-map";
@use "event-detail";
```

### コンパイル後の CSS の扱い

`css/style.css` は**コミットします**（GitHub Pages でそのまま公開できる状態を保つため）。ただし次のルールを守ってください。

- **`css/style.css` を手で編集しない。** 直したいときは必ず SCSS を直して再コンパイルする
- **`css/style.css` でコンフリクトが起きたら、中身を手で解決しない。** SCSS 側のコンフリクトだけを解決し、再コンパイルして丸ごと上書きする

```bash
# style.css でコンフリクトしたときの手順
git checkout --ours css/style.css   # いったんどちらかに寄せる（中身は捨てる前提）
# → SCSS のコンフリクトを解決して保存
# → Live Sass Compiler が再コンパイルする（＝正しい style.css ができる）
git add css/style.css
```

### JavaScript

- フレームワークもビルドも使いません。`js/` 配下に素の JS を置きます
- 外部ライブラリは CDN で `<script>` 読み込みします
- `var` は使わず `const` / `let` を使います
- グローバル変数を増やさないよう、処理は関数か `DOMContentLoaded` の中にまとめます

### 地図（Leaflet）

```html
<link
  rel="stylesheet"
  href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
```

- バージョンは **1.9.4 に固定**します。各自でバージョンを変えないでください
- OpenStreetMap を使うときは、地図の隅に必ずクレジット表記を出します（Leaflet の `attribution` オプション。ライセンス上の義務です）

### 画像

**形式**

| 用途 | 形式 |
| --- | --- |
| 写真（会場、競技、チーム、背景） | **WebP** |
| 透過が必要な画像、スクリーンショット | **PNG** |
| アイコン、ロゴ | **SVG** |

WebP を基本にします。PNG は透過が必要なときに使ってください。写真を JPEG のまま置かないでください。

**配置先**

`images/` の下に、用途別のフォルダを作ります。

```
images/
├── common/    ← ロゴ、全ページ共通の画像
├── icons/     ← アイコン（SVG）
├── top/       ← トップページ専用
├── sports/    ← 競技別の画像
├── teams/     ← チームロゴ、チーム画像
└── events/    ← 試合・イベント画像
```

**サイズの目安**

- 1 枚 **300KB 以下**を目安にする
- 表示サイズの 2 倍を超える巨大な画像をそのまま置かない（表示 400px なら書き出しは 800px 程度まで）
- `<img>` には必ず `alt` と `width` / `height` を書く（レイアウトがガタつくのを防ぐため）

---

## 4. PHP・データベースのルール

SPOTIVE は PHP と MySQL を使います。**データベースの正は `db/schema.sql` です。**
テーブルやカラムを増やしたくなったら、自分のコードで回避せず、まず `schema.sql` を直してチームに共有してください。

ここに書かれているルールは、**個人情報と認証を扱うため**のものです。SPOTIVE は本人確認書類（`identity_verifications`）、電話番号、パスワードを持つので、書き方を間違えると事故になります。面倒でも守ってください。

### データベースの現状（重要）

**`db/schema.sql` は、今のところ「主催者が大会を掲載し、審査を通す」部分しか設計されていません。**
ユーザー登録・本人確認・主催者認証・大会の確認（Lv.1〜4）と、公開用ビュー `v_public_tournaments` があります。

一方で、企画書の主役である**観戦者向けの「試合」データ（プロ野球・Bリーグ・Jリーグの試合、チケット価格帯、チーム、会場）のテーブルはまだありません。**
`data/matches.json` `data/teams.json` `data/areas.json` は、画面を作るための**仮データ**です。DB ができるまでの置き換え用と考えてください。

そのため、今の段階では次のように扱います。

- **主催者が掲載した大会** … `v_public_tournaments` から取る（下記のルールに従う）
- **観戦用の試合データ** … `data/*.json` の仮データで画面を作る
- **観戦用のテーブル設計は、まだ各自で作らない。** 必要になったらチームで `schema.sql` に追加します

自分の担当ページのためだけに勝手なテーブルを足すと、あとで統合できなくなります。必ず相談してください。

### ファイル構成

```
index.php              ← トップページ
config/
└── db.php             ← DB 接続（PDO）。全ページここから読み込む
db/
└── schema.sql         ← テーブル定義。これが正
pages/                 ← 各ページ（PHP が必要なものは .php）
```

- **PHP の処理が必要なページだけ `.php`**、静的なページは `.html` のままで構いません
- ファイル名の付け方は HTML と同じです（英小文字＋ハイフン。`match-detail.php`）
- インデントはスペース2、これも他と同じです

### 基本の書き方

```php
<?php

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';

// ① ここでデータを取得する（HTML は書かない）
$tournaments = ...;

?>
<!DOCTYPE html>
<html lang="ja">
  <!-- ② ここから下は表示だけ（SQL は書かない） -->
</html>
```

- **ファイルの先頭で `declare(strict_types=1);` を書く**
- **上半分でデータ取得、下半分で表示**。この 2 つを混ぜない。HTML の途中に SQL を書かない
- 他のファイルを読み込むときは `require_once __DIR__ . '/...'` を使う（相対パスだけだと、どこから呼ばれるかで壊れます）
- **ファイル末尾に閉じタグ `?>` を書かない**（後ろに空白や改行が混ざると、余計な出力になってヘッダー送信のエラーになります）
- `<?php` と `<?=` は使ってよい。**`<?` （短縮タグ）は使わない**（環境によって動きません）

HTML の中で PHP を書くときは、閉じ方が分かる**代替構文**を使います。

```php
<?php if ($tournaments === []) : ?>
  <p class="match-list__empty">見つかりませんでした。</p>
<?php else : ?>
  <?php foreach ($tournaments as $t) : ?>
    <li class="match-list__item"><?= h($t['title']) ?></li>
  <?php endforeach; ?>
<?php endif; ?>
```

`{ }` ではなく `: ... endif;` `: ... endforeach;` を使ってください。HTML と混ざったときに、どこで閉じているかが追えます。

### 出力は必ずエスケープする

**画面に出す値は、例外なく `h()` を通します。**

```php
/** HTML エスケープ。出力時は必ずこれを通す */
function h(?string $value): string
{
  return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
```

```php
<!-- ○ -->
<h3><?= h($t['title']) ?></h3>
<a href="match-detail.php?id=<?= (int) $t['id'] ?>">詳細</a>

<!-- × そのまま出さない -->
<h3><?= $t['title'] ?></h3>
```

- 文字列は `h()`、数値は `(int)` / `(float)` でキャストしてから出す
- **DB から取った値も必ずエスケープします。** 大会名やニックネームはユーザーが入力したものなので、`<script>` を書き込まれる可能性があります
- URL に値を入れるときも同じ。`h()` か `urlencode()` を通す

### SQL は必ずプレースホルダを使う

**変数を SQL の文字列に連結しないでください。** 例外はありません。

```php
// ○ プレースホルダを使う
$stmt = $pdo->prepare(
  'SELECT id, title FROM v_public_tournaments WHERE venue_prefecture = :pref'
);
$stmt->execute([':pref' => $pref]);
$rows = $stmt->fetchAll();

// × 連結する（SQL インジェクション）
$rows = $pdo->query("SELECT id, title FROM v_public_tournaments WHERE venue_prefecture = '$pref'");
```

- カラム名や `ORDER BY` はプレースホルダにできません。**並び順を切り替えたいときは、許可する値の配列を用意して、その中から選ぶ**形にします

```php
$allowed = ['starts_at' => 'starts_at ASC', 'fee' => 'entry_fee_yen ASC'];
$order   = $allowed[$_GET['sort'] ?? ''] ?? 'starts_at ASC';
```

### データベース接続

- 接続は **`config/db.php` の `db()` 関数だけ**を使います。各ページで `new PDO(...)` を書かないでください
- 接続オプションは `config/db.php` で統一しています。ページ側で変えないこと
  - `ERRMODE_EXCEPTION`（エラーを例外にする）
  - `EMULATE_PREPARES = false`（プレースホルダを MySQL 側で処理する。これが false でないとプレースホルダの意味が薄れます）
  - `charset=utf8mb4`、`time_zone = '+09:00'`（`schema.sql` と揃えています）
- **接続情報を書き換えたファイルをコミットしないでください。** 自分の環境だけパスワードが違う場合は、コミットに含めないよう気をつけること

### 公開する大会は必ずビューから取る

`schema.sql` には公開用のビュー **`v_public_tournaments`** があります。**主催者が掲載した大会**を画面に出すときは、**`tournaments` テーブルを直接見ずに、このビューを使ってください。**

```php
// ○
$pdo->query('SELECT * FROM v_public_tournaments WHERE ...');

// × 下書きや中止の大会まで出てしまう
$pdo->query('SELECT * FROM tournaments WHERE ...');
```

ビューは次をやってくれます。

- `status = 'published'` の大会だけに絞る（`draft` や `cancelled` を公開しない）
- `is_verified`（Lv.4 の確認済みフラグ）を付ける
- `organizer_name` を出す（`organizer_profiles.display_name` があればそれ、無ければ `users.nickname`）

直接 `tournaments` を触ってよいのは、主催者本人の管理画面（自分の下書きを見る）と、審査画面だけです。

### 信頼レベル（Lv.1〜4）の扱い

`schema.sql` の設計に合わせます。**自分で判定ロジックを作らないでください。**

| レベル | 見る場所 |
| --- | --- |
| Lv.1 一般ユーザー | `users.trust_level = 1` |
| Lv.2 本人確認済み | `users.trust_level = 2` |
| Lv.3 主催者認証済み | `users.trust_level = 3`（資格の有効性は `organizer_profiles.status = 'active'`） |
| Lv.4 大会確認済み | `tournaments.verification_status = 'verified'`（ビューでは `is_verified`） |

- Lv.4 は**ユーザーではなく「大会」に付く**レベルです。混同しないこと
- 主催者としての操作を許可する前に、`trust_level` だけでなく `organizer_profiles.status` と `verified_until` も確認します（停止・期限切れがあります）

### パスワードと認証情報

- パスワードは **`password_hash($password, PASSWORD_DEFAULT)`** で保存し、照合は **`password_verify()`** を使います。`md5()` / `sha1()` は使いません
- **トークンやコードを平文で保存しない。** `schema.sql` が `token_hash CHAR(64)` `code_hash CHAR(64)` になっているのは、SHA-256 のハッシュだけを保存する設計だからです。平文はメールや SMS で送るだけで、DB には入れません
- メールアドレスは `email`（入力そのまま）と `email_normalized`（小文字化＋trim）の両方を入れます。重複判定は `email_normalized` で行います
- 電話番号は **E.164 形式**（`+819012345678`）で `phone_e164` に入れます。`090-1234-5678` のまま保存しない

### 個人情報・アップロードファイル

- **本名と生年月日は `users` に入れません。** 確認済みの氏名・生年月日は `identity_verifications` 側です（`users.birthdate` は自己申告の値）
- 本人確認書類などのアップロードは `attachments` に記録し、**実ファイルは公開ディレクトリの外**に置きます。`images/` に本人確認書類を置かないでください。URL を知られたら誰でも見られます
- 本名、生年月日、電話番号、書類の中身を、**ログや `error_log` に出さない**こと

### エラーの扱い

- **エラーの内容を画面に出さない。** SQL 文やファイルパスが見えると、攻撃の手がかりになります
- 画面には「読み込めませんでした」程度の案内を出し、詳細は `error_log()` に送ります

```php
try {
  $rows = ...;
} catch (PDOException $e) {
  error_log('[SPOTIVE] DB error: ' . $e->getMessage());
  $dbError = true;  // 画面には案内文だけ出す
}
```

- **DB に繋がらなくても、ページが白画面にならないようにします。** 一覧が空の状態で描画されるようにしておけば、DB がまだ無いメンバーでもレイアウトの確認ができます

### 受け取った値の扱い

- `$_GET` / `$_POST` は**そのまま使わない**。必ず型を決めて受け取ります

```php
$pref   = trim((string) ($_GET['pref'] ?? ''));
$maxFee = filter_var($_GET['max_fee'] ?? null, FILTER_VALIDATE_INT);
$id     = (int) ($_GET['id'] ?? 0);
```

- フォームの送信（登録・更新・削除）は **POST** を使います。GET で更新しないこと
- ログイン後・登録後は **`header('Location: ...'); exit;` でリダイレクト**します（リロードで二重送信されるのを防ぐため）

---

## 5. コーディング規約

### class の命名規則：BEM

`block__element--modifier` の形式で書きます。

```html
<article class="match-card match-card--sold-out">
  <img class="match-card__image" src="..." alt="" />
  <div class="match-card__body">
    <h3 class="match-card__title">横浜 vs 東京</h3>
    <p class="match-card__date">2026年10月3日 18:00</p>
    <span class="match-card__tag">サッカー</span>
  </div>
</article>
```

ルール：

- **Block** … 独立した部品。`match-card`、`search-filter`、`site-header`
- **Element** … Block の中の部品。`__` でつなぐ。`match-card__title`
- **Modifier** … 見た目や状態の違い。`--` でつなぐ。`match-card--sold-out`
- 単語が複数のときはハイフンでつなぐ（`match-card`、`site-header`）
- **Element を入れ子にしない。** `match-card__body__title` は書かず、`match-card__title` にする
- JS から操作する状態は `is-active` / `is-open` / `is-hidden` の状態クラスを使う
- **`id` にスタイルを当てない。** `id` はリンク先のアンカーと JS の取得用だけに使う

SCSS 側では `&` を使って書きます。

```scss
.match-card {
  padding: 16px;
  border-radius: $radius-lg;

  &__title {
    font-family: $font-heading;
    font-size: 18px;
  }

  &--sold-out {
    opacity: 0.6;
  }
}
```

- **ネストは 3 階層まで。** それ以上深くなったら Block を分けるサインです
- `!important` は使いません

### インデント

**スペース 2**で統一します。HTML / SCSS / JS すべて同じです。タブは使いません。

VSCode の画面右下で「スペース: 2」になっているか確認してください。

### ファイル・フォルダの命名規則

**基本ルール**

- ファイル名には**英小文字のみ**を使う
- 複数の単語は**ハイフン（`-`）で区切る**
- **日本語、スペース、大文字は使わない**
- ファイル名から、そのファイルの役割や用途が分かるように命名する
- 既存のファイル名を変更する場合は、関連するファイルの参照パスも必ず確認・修正する

> アンダースコア（`_`）は、**SCSS のパーシャルの先頭に付ける場合のみ**使います（`_variables.scss`）。これは Sass の仕様で、`_` が付いたファイルだけが「CSS を出力しない部品」として扱われるためです。それ以外の場所（HTML / JS / 画像）では使いません。

**HTML・SCSS・JavaScript**

同じページや機能に関連するファイルは、**同じ名前**を使います。

```
event-detail.html
event-detail.scss   （実際のファイル名は _event-detail.scss）
event-detail.js
```

ページ単位のファイル名は、ページや機能の内容を表す名前にします。

```
sports-map.html
event-detail.html
team-detail.html
favorite.html
profile.html
organizer-login.html
organizer-register.html
```

**共通ファイル**

複数のページで共通して使うファイルには、内容が分かる名前を付けます。

```
common.js
_common.scss
_header.scss
_footer.scss
_button.scss
_reset.scss
_variables.scss
```

**画像・アイコン**

画像やアイコンも、用途が分かるように英小文字とハイフンで命名します。

```
icon-search.svg
icon-map.svg
icon-heart.svg
bg-map.webp
team-logo.webp
event-image.webp
```

同じ種類の画像が複数ある場合は、**3 桁の連番**を使います。

```
team-001.webp
team-002.webp
team-003.webp
```

**禁止例**

```
× SportsMap.html      （大文字）
× sports_map.html     （アンダースコア）
× スポーツマップ.html  （日本語）
× sports map.html     （スペース）

○ sports-map.html
```

大文字を混ぜると、Mac（大文字小文字を区別しない）では動くのにサーバーでは 404 になる、という事故が起きます。必ず小文字で統一してください。

ファイルを新しく作る場合は、既存のファイル構成や命名規則を確認し、SPOTIVE 全体で統一された命名になるようにしてください。

### コミットメッセージ

`種別: 内容` の形式で書きます。**内容は日本語で構いません。**

```
feat: 試合検索フィルターを追加
fix: スマホでヘッダーが重なる不具合を修正
style: 試合カードのボタンの余白を調整
docs: AGENTS.md にフォントのルールを追記
refactor: 地図のピン生成処理を関数に切り出し
chore: Leaflet を CDN から読み込むよう設定
```

| 種別 | 使う場面 |
| --- | --- |
| `feat` | 新しい機能・ページ・パーツを追加した |
| `fix` | 不具合を直した |
| `style` | 見た目だけの調整（動作は変わらない） |
| `docs` | ドキュメントの変更 |
| `refactor` | 動作は変えずに書き方を整理した |
| `chore` | 設定ファイル、ライブラリの追加など |

- 1 コミットに 1 つの意味の変更だけを入れる。「トップ修正といろいろ」のようなコミットは避ける
- 「修正」「更新」だけのメッセージは書かない。**何を**どうしたかを書く

### ブランチと Pull Request

`main` に直接 push しません。必ずブランチを切って Pull Request を出します。

```
feature/sports-map        新しいページ・機能
fix/header-overlap        不具合修正
```

- PR には「何を変えたか」「どのページを見れば確認できるか」を書く
- 可能ならスクリーンショット（スマホ幅・PC 幅）を貼る
- **自分以外のメンバーが 1 人以上見てから** マージする
- 共通ファイル（`_variables.scss`、`_header.scss`、`_footer.scss`、`common.js`）を変更する PR は、必ずチームに一声かける

---

## 6. 作業後に必ずやること（提出前チェックリスト）

コミット・PR の前に、**毎回**次を確認してください。ビルドコマンドはありません。

- [ ] **SCSS を保存して、`css/style.css` が更新されたか確認した**
      （Live Sass Compiler が動いていないと、CSS が古いまま提出されます）
- [ ] **`css/style.css` を手で編集していない**
- [ ] **ブラウザをスーパーリロードして表示を確認した**
      （Windows: `Ctrl + Shift + R` / Mac: `Cmd + Shift + R`。キャッシュで古い CSS が残ります）
- [ ] **375px / 768px / 1280px の 3 幅で確認した**
      （検証ツール `F12` → デバイスツールバー `Ctrl + Shift + M`）
      - [ ] 横スクロールが出ていない
      - [ ] 文字が見切れていない、要素が重なっていない
- [ ] **`F12` の Console にエラー（赤い表示）が出ていない**
- [ ] **画像が全部表示されている**（画像切れアイコンが出ていない）
- [ ] **リンクが切れていない**（`href="#"` のまま放置していない）
- [ ] **色・余白・角丸が、このファイルで決めた値になっている**
      （カラーコードを直書きしていない、8 の倍数以外の余白を使っていない）
- [ ] **ファイル名が命名規則に従っている**（小文字・ハイフン）

PHP を触ったときは、加えて次も確認してください。

- [ ] **`php -l ファイル名` で構文エラーが出ない**
      （VSCode のターミナルで実行。エラーがあると画面が真っ白になります）
- [ ] **画面に出す値を全部 `h()` に通している**（数値は `(int)` キャスト）
- [ ] **SQL に変数を連結していない**（プレースホルダになっている）
- [ ] **`new PDO(...)` をページに直接書いていない**（`db()` を使っている）
- [ ] **公開一覧は `v_public_tournaments` から取っている**
- [ ] **エラーの詳細が画面に出ていない**（SQL 文やファイルパスが見えていない）
- [ ] **ファイル末尾に `?>` を書いていない**
- [ ] **接続情報を書き換えた `config/db.php` をコミットしていない**
- [ ] **本名・電話番号・書類の内容をログに出していない**

---

## 7. 迷ったときは

- このファイルに書かれていないルールが必要になったら、**勝手に決めずにチームで決めて、このファイルに追記する**
- 既存のルールを変えたいときも、まず相談する。自分のページだけ別ルールにしない
- ライブラリを新しく追加したいときは、必ずチームに共有してから入れる

このファイルを更新したときは、`docs: ` のコミットで変更内容が分かるようにしてください。
