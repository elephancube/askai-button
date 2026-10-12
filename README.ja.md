# askai-button

[English](README.md) | 日本語

Webページに「AIに質問」ボタンを表示する部品です。訪問者が質問を入力し、ChatGPT・Claude・Google AIモードから使うサービスを選ぶと、質問とサイトのURLを入れた状態でそのサービスが別タブで開きます。AIのAPIは呼びません。部品自身は何も保存・送信しません。

1つのTypeScriptソースから、単一ファイルのスクリプト（グローバル `AskAIButton`）、npmのESMパッケージ、薄いWordPressプラグインを作っています。

ドキュメントとデモ: https://elephancube.github.io/askai-button/ （[導入方法](https://elephancube.github.io/askai-button/install/)、[オプションのリファレンス](https://elephancube.github.io/askai-button/reference/)、[WordPress](https://elephancube.github.io/askai-button/wordpress/)）

## AIに渡す内容

選んだサービスのURLパラメータには、テンプレートから作ったテキストが入ります。日本語の標準テンプレートは次のとおりです。

```
{question}

この質問について、以下のURLを参照して回答してください。
{site_url}
```

`{question}` は訪問者が入力した質問です。`{site_url}` は、`siteUrl` を指定しなければ現在のオリジンのルートです。テンプレートも各ラベルの文言も、オプションで差し替えられます。

## すぐに使う

scriptタグで、ファイルを自分のサーバーに置く場合（`askai-button.min.js` は[リリースページ](https://github.com/elephancube/askai-button/releases)またはドキュメントサイトからダウンロードできます）:

```html
<script src="askai-button.min.js" data-askai></script>
```

scriptタグで、CDN から読み込む場合（jsDelivr。unpkg では `https://unpkg.com/@elephancube/askai-button@0.1.0/dist/askai-button.min.js` で同じファイルを配信しています）:

```html
<script src="https://cdn.jsdelivr.net/npm/@elephancube/askai-button@0.1.0/dist/askai-button.min.js" data-askai defer></script>
```

CDN を使うと、訪問者のブラウザーが CDN の運営者（第三者）からスクリプトを取得します。ボタン自体は、この場合も何も送信しません。ファイルを自分のサーバーに置けば、この通信は発生しません。URLのバージョンは正確に指定したままにしてください。バージョンを書かないURLや `@latest` を付けたURLでは、新しいバージョンが公開されるたびに、サイト上のスクリプトが変わります。Subresource Integrity と Content Security Policy については、[導入方法のページ](https://elephancube.github.io/askai-button/install/#cdn)で説明しています。

npmの場合（パッケージは `@elephancube` スコープで公開しています。プロジェクトとファイルの名前は askai-button です）:

```sh
npm install @elephancube/askai-button
```

```js
import { init } from '@elephancube/askai-button';

init({
  presets: ['このサイトでできることは？', '料金はいくらですか？'],
  providers: ['chatgpt', 'claude'],
});
```

ボタンの言語はページの言語に従います（`lang` オプション、`<html lang>`、ブラウザの言語、`en` の順）。日本語と英語を内蔵しています。完全なページ例は [`examples/`](examples/) にあります。

## WordPress

プラグインは [`wordpress/askai-button/`](wordpress/askai-button/) にあります。ボタンの表示と設定画面（設定 > AIに質問）を追加します。設定でオンにした場合に限り（標準はオフ）、ボタンと各AIサービスが使われた回数の月ごとの合計を、サイト自身のデータベースに保存します。質問の内容や訪問者に関する情報は保存しません。導入方法などは [`readme.txt`](wordpress/askai-button/readme.txt)（英語）と、ドキュメントサイトの [WordPress のページ](https://elephancube.github.io/askai-button/wordpress/)を参照してください。

## しないこと

- AIのAPIを呼ばず、APIキーも要りません。
- サーバーを持たず、部品自身はネットワーク通信をしません（スクリプトファイルの読み込みは、ファイルの置き場所への通信です。自分のサーバーか、CDN を選んだ場合はその CDN になります）。
- 質問を計測・保存せず、Cookieも使いません。
- 保存するのは、訪問者が最後に選んだサービスだけです（`localStorage`）。

## 注意点

- 各サービスが受け付ける `q` URLパラメータは公式に文書化されておらず、予告なく変わったり使えなくなったりすることがあります。URLテンプレートはすべて設定で変更でき、サービスごとに「テキストをクリップボードにコピーしてサービスを開く」方式にも切り替えられます。
- AIサービスがあなたのサイトを取得できる必要があります。AIクローラーを拒否しているサイト、ログインが必要なサイト、インターネットから到達できないサイトは読めません。パネルには、質問とURLをコピーして手動で貼り付けるためのボタンがあります。
- 特定のサービスがそのURLを受け付けることは保証しません。提供するサービスは、ご自身で動作を確認してください。

## リポジトリの構成

- `packages/core/` - ライブラリ本体（TypeScript、ランタイム依存なし）。フロントの挙動はすべてここにあります。
- `wordpress/askai-button/` - WordPressプラグイン。設定をライブラリに渡します。
- `site/` - ドキュメントとデモのサイト。`site/content/` の Markdown を、`site/build.mjs` が `site/dist/` に出力します。
- `examples/` - 素のHTMLの例（先にビルドが必要です）。
- `scripts/` - ビルド補助スクリプト。
- `.github/workflows/` - CI（`ci.yml`）と、サイトを GitHub Pages に公開する処理（`pages.yml`）。

## 開発

```sh
npm install
npm test              # ユニットテスト（Vitest）
npm run typecheck
npm run build         # スクリプトをビルドしてプラグインにコピーし、サイトをビルド
npm run build:site    # サイトだけをビルド（site/dist に出力）
npm run site          # すべてビルドしてから、サイトを手元で配信
npm run serve:site    # ビルド済みのサイトを手元で配信
npm run test:php      # プラグインのテスト（PHPが必要）
npm run i18n          # プラグインの翻訳ファイルを再生成
npm run zip:plugin    # dist-plugin/askai-button-<version>.zip を作成
```

[CONTRIBUTING.md](CONTRIBUTING.md)（英語、末尾に日本語の要約あり）を参照してください。セキュリティ上の問題の報告は [SECURITY.md](SECURITY.md) を参照してください。

## ライセンス

- コアライブラリ（`wordpress/` の外のすべて）は MIT ライセンスです。[LICENSE](LICENSE) を参照してください。
- `wordpress/askai-button/` の WordPress プラグインは GPL-2.0-or-later です。[LICENSE](wordpress/askai-button/LICENSE) を参照してください。

Copyright (c) 2026 Elephancube Inc.（株式会社エレファンキューブ）。無料のオープンソースで、有料版はありません。サポートはベストエフォートです。

## 商標

ChatGPT、Claude、Google は、それぞれの権利者の商標です。本プロジェクトはこれらの企業と提携しておらず、承認も受けていません。各サービスのロゴは同梱していません。
