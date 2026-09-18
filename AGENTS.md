# AGENTS.md — SPOTIVE 制作ルール

このファイルは SPOTIVE をグループで制作するための共通ルールです。
人間のメンバーも AI エージェントも、作業前に必ずこのファイルを読んでください。
判断に迷ったら、ここに書かれているルールを優先します。ここに書かれていないことは、勝手に決めずにチームに確認してください。

---

## 1. プロジェクト概要

### 何を作るか

SPOTIVE は、全国のスポーツ観戦情報を**地図上から簡単に探せる**スポーツ観戦マップアプリです。

野球・サッカー・バスケットボール・バレーボールなど複数のスポーツの試合情報をまとめ、開催地域・日時・競技・料金などの条件から、自分に合った観戦を探せることを目指します。
試合情報だけでなく、会場へのアクセス、周辺施設、ホテル、天気など、スポーツ観戦に必要な情報もまとめて提供します。

コンセプトは「**スポーツを、もっと身近な日常に**」です。

### ターゲット

**一般ユーザー**

- スポーツ観戦が好きな人
- 複数の競技を観戦する人
- 遠征する人
- 初めてスポーツ観戦をする人
- 学生や家族

解決したい課題：

- 「近くで観戦できる試合を探したい」
- 「遠征先でも別の試合を観戦したい」
- 「予算内で観戦できる試合を探したい」

**主催者アカウント**

一般ユーザーとは別に、試合やイベント情報を掲載できる主催者向けアカウントを用意します。
想定する掲載者は、個人、団体・チーム、学校、企業、スポーツ協会・連盟などです。

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
| HTML | 素の HTML5（テンプレートエンジンなし） |
| CSS | **SCSS**（Live Sass Compiler でコンパイル） |
| JavaScript | **素の JavaScript**（フレームワークなし） |
| 地図 | **Leaflet**（CDN 読み込み、OpenStreetMap、API キー不要） |
| ビルドツール | なし（npm / Vite / webpack は使わない） |

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

## 4. コーディング規約

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

## 5. 作業後に必ずやること（提出前チェックリスト）

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

---

## 6. 迷ったときは

- このファイルに書かれていないルールが必要になったら、**勝手に決めずにチームで決めて、このファイルに追記する**
- 既存のルールを変えたいときも、まず相談する。自分のページだけ別ルールにしない
- ライブラリを新しく追加したいときは、必ずチームに共有してから入れる

このファイルを更新したときは、`docs: ` のコミットで変更内容が分かるようにしてください。
