---
title: 導入方法
description: askai-button をサイトに入れる方法は4通りあります。ファイルを自分のサーバーに置いて script タグで読み込む方法、CDN から script タグで読み込む方法、npm パッケージを使う方法、WordPress プラグインを使う方法です。それぞれの手順と、動作の確認方法を説明します。
nav: 導入方法
---

どの方法でも、動くものは同じです。サイトの作りに合うものを選んでください。

| 方法 | 向いている場合 |
| --- | --- |
| [script タグ（ファイルを自分のサーバーに置く）](#script) | HTMLを直接編集できるサイト。ビルドの仕組みがなくても使えます。第三者への通信は発生しません。 |
| [script タグ（CDN から読み込む）](#cdn) | すぐに試したい場合や、ファイルをアップロードできないサイト。訪問者のブラウザーが CDN からファイルを取得します。 |
| [npm](#npm) | JavaScript をバンドルしているサイトやWebアプリ。 |
| [WordPress プラグイン](#wordpress) | WordPress のサイト。管理画面で設定できます。 |

## script タグで入れる {#script}

### 1. ファイルを自分のサイトに置く

[askai-button.min.js をダウンロード]({{file:askai-button.min.js}})して、自分のサイトのサーバーに置きます（{{min_kb}} KB、gzip 圧縮後は約 {{gzip_kb}} KB）。置き場所は自由です。ここでは `/js/askai-button.min.js` に置いたものとします。

このファイル1つに、ボタンの動作とCSSがすべて入っています。ほかに読み込むものはありません。

### 2. タグを1行追加する

ボタンを表示したいページに、次の1行を追加します。

```html
<script src="/js/askai-button.min.js" data-askai></script>
```

`data-askai` を付けると、読み込んだ時点でボタンが表示されます。タグを書く場所は、`<head>` の中でも `</body>` の直前でも構いません。`defer` を付けても動きます。

### そのまま動く最小の例

次のHTMLを保存し、同じフォルダーに `askai-button.min.js` を置くと動きます。

```html
<!doctype html>
<html lang="ja">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>サンプルページ</title>
  </head>
  <body>
    <h1>サンプルページ</h1>
    <p>右下のボタンを押すと、質問の入力欄が開きます。</p>

    <script src="askai-button.min.js" data-askai
      data-site-url="https://example.com/"
      data-presets="このサイトでは何ができますか？|料金を教えてください"></script>
  </body>
</html>
```

`data-site-url` は、AIに渡すサイトのURLです。Webサーバーで公開しているページでは省略でき、そのときはサイトのトップ（`https://あなたのドメイン/`）が自動で使われます。この例では、ファイルを直接開いて試せるように指定しています。

### よく使う属性

設定は `data-*` 属性で指定します。

| 属性 | 内容 | 例 |
| --- | --- | --- |
| `data-presets` | 質問の例。`\|` で区切って6件まで。 | `data-presets="料金は？\|納期は？"` |
| `data-site-url` | AIに渡すサイトのURL。サイトがドメインの直下にない場合に指定します。 | `data-site-url="https://example.com/shop/"` |
| `data-providers` | 表示するAIサービスと順序。`,` で区切ります。 | `data-providers="chatgpt,claude"` |
| `data-position` | ボタンの位置。 | `data-position="bottom-left"` |
| `data-accent-color` | ボタンの色。 | `data-accent-color="#27455c"` |
| `data-lang` | 言語を固定する場合に指定します。通常は不要です。 | `data-lang="en"` |

すべての属性は「[リファレンス]({{page:reference}}#data-attributes)」にあります。

### JavaScript で設定する

文言の変更やAIサービスの追加など、属性では指定できない設定は、`data-askai` を付けずに読み込んで `AskAIButton.init()` を呼びます。

```html
<script src="/js/askai-button.min.js"></script>
<script>
  AskAIButton.init({
    presets: ['このサイトでは何ができますか？', '料金を教えてください'],
    providers: ['chatgpt', 'claude'],
    labels: { buttonLabel: 'AIに聞いてみる' },
  });
</script>
```

## CDN から読み込む {#cdn}

npm に公開したバージョンは、公開 CDN の jsDelivr と unpkg からも配信されます。ファイルをアップロードする必要はなく、次の1行だけで使えます。

```html
<script src="https://cdn.jsdelivr.net/npm/askai-button@{{version}}/dist/askai-button.min.js" data-askai defer></script>
```

URLには、正確なバージョン（`@{{version}}`）が入っています。このようなURLで配信されるファイルの内容は、あとから変わりません。`data-*` 属性や `AskAIButton.init()` の使い方は、[上の説明](#script)と同じです。

unpkg から読み込む場合は、次のとおりです。

```html
<script src="https://unpkg.com/askai-button@{{version}}/dist/askai-button.min.js" data-askai defer></script>
```

### 自分のサーバーに置く場合との違い

- 訪問者のブラウザーが、CDN にファイルを取りに行きます。そのため、CDN の運営者（第三者）に、ブラウザーが通常のリクエストで送る情報（IPアドレスや、ページのリファラーの設定によってはサイトのURLなど）が伝わります。プライバシーポリシーを公開しているサイトでは、記載が必要かどうかを確認してください。
- ボタンの中身は同じファイルで、ボタン自体は何も送りません。質問の送信も、計測も、独自の通信もありません。
- CDN につながらないときは、ボタンが表示されません。ページのほかの部分には影響しません。

第三者への通信をまったく発生させたくない場合は、[ファイルを自分のサーバーに置いてください](#script)。このWebサイトも、そうしています。

### バージョンを固定する

公開中のサイトでは、バージョンを書かないURLや `@latest` を付けたURLを使わないでください。こうしたURLは、新しいバージョンが公開されるとすぐにそちらを配信するため、サイトの運営者が何もしなくても、ボタンの動作や見た目が変わることがあります。正確なバージョンを指定しておけば、更新する時期を自分で決められます。更新するときは、[変更履歴]({{repo}}/blob/main/CHANGELOG.md)を確認してから、URLのバージョン番号を書き換えます。

`@0.1` のような範囲の指定（jsDelivr）も、その範囲の中で新しいバージョンに切り替わるので、同じ理由で避けてください。

### Subresource Integrity（SRI）

`integrity` 属性を付けると、ファイルの内容が想定と少しでも違う場合に、ブラウザーがそのファイルを実行しなくなります。正確なバージョンを指定している場合にだけ意味があります。値はファイルごとに決まるため、このページには載せていません。値は次のコマンドで計算できます。

```sh
curl -s https://cdn.jsdelivr.net/npm/askai-button@{{version}}/dist/askai-button.min.js | openssl dgst -sha384 -binary | openssl base64 -A
```

出力された値を `sha384-` のあとに書き、`crossorigin="anonymous"` も付けます。

```html
<script src="https://cdn.jsdelivr.net/npm/askai-button@{{version}}/dist/askai-button.min.js"
  integrity="sha384-（コマンドで得た値）" crossorigin="anonymous"
  data-askai defer></script>
```

### Content Security Policy

Content Security Policy を設定しているサイトでは、`script-src` で CDN のホストを許可してください。jsDelivr は `https://cdn.jsdelivr.net`、unpkg は `https://unpkg.com` です。

## npm で入れる {#npm}

```sh
npm install askai-button
```

```js
import { init } from 'askai-button';

init({
  presets: ['このサイトでは何ができますか？', '料金を教えてください'],
});
```

- ES モジュールとして提供しています。TypeScript の型定義も入っています。
- `init()` は、ブラウザーの中で一度だけ呼びます。サーバー側で読み込んでもエラーにはならず、何もしません。
- もう一度 `init()` を呼ぶと、ボタンは新しい設定で作り直されます。取り除くときは `destroy()` を呼びます。
- CommonJS 用のビルドはありません。`require()` を使っているプロジェクトでは、バンドラー、新しいバージョンの Node.js、または `await import('askai-button')` を使ってください。
- パッケージの `dist/askai-button.min.js` は、script タグ用のファイルと同じものです。[CDN](#cdn) が配信するのも、このファイルです。

React などのコンポーネントから使うときは、表示されたあとに一度だけ呼びます。

```js
import { useEffect } from 'react';
import { init, destroy } from 'askai-button';

export function AskAI() {
  useEffect(() => {
    init({ presets: ['料金を教えてください'] });
    return () => destroy();
  }, []);
  return null;
}
```

オプションの一覧は「[リファレンス]({{page:reference}}#options)」にあります。

## WordPress プラグインで入れる {#wordpress}

必要な環境は、WordPress 6.4 以上、PHP 7.4 以上です。

1. プラグインの zip ファイル（`askai-button-{{version}}.zip`）を、[GitHub のリリースページ]({{repo}}/releases)からダウンロードします。
2. WordPress の管理画面で「プラグイン > 新規プラグインを追加 > プラグインのアップロード」を開き、zip ファイルを選んでインストールします。
3. 「プラグイン」の一覧で「AskAI Button」を有効化します。
4. サイトを開くと、公開ページの右下にボタンが表示されます。設定は「設定 > AIに質問」で変更できます。

有効化するだけで、標準の設定のまま使い始められます。設定画面の内容は「[WordPress]({{page:wordpress}})」のページで説明しています。

プラグインは GitHub で配布しています。WordPress.org のプラグインディレクトリには登録していません。

### ソースから zip を作る

リポジトリを取得して、次のコマンドを実行すると、`dist-plugin/askai-button-{{version}}.zip` ができます。Node.js が必要です。

```sh
git clone {{repo}}.git
cd askai-button
npm install
npm run build
npm run zip:plugin
```

## 動作を確認する {#check}

1. ページの右下に「AIに質問」ボタンが表示される（英語のページでは「Ask AI」）。
2. ボタンを押すとパネルが開き、質問の入力欄にカーソルが入る。
3. 質問を入力すると、いちばん下の送信ボタンが押せるようになる。
4. 送信ボタンを押すと、選んだAIサービスが別のタブで開き、質問・定型文・サイトのURLが渡されている。
5. 渡されたURLが、あなたのサイトのトップのURLになっている。

5 のURLが違う場合は、`data-site-url`（`init()` では `siteUrl`）で指定してください。サイトがドメインの直下ではなく、`https://example.com/shop/` のような下の階層にあるときに必要です。

### ボタンが表示されないとき

- ファイルのパスが正しいか（ブラウザーの開発者ツールで、`askai-button.min.js` が読み込まれているか）を確認します。CDN のURLを使っている場合は、URLのバージョン番号も確認します。存在しないバージョンを指定すると、ファイルは見つかりません。
- script タグに `data-askai` が付いているか、または `AskAIButton.init()` を呼んでいるかを確認します。
- ページに `<meta name="askai" content="off">` があると、ボタンは表示されません。
- `providers` に、存在しないAIサービスのidだけを指定していないかを確認します。使えるAIサービスが1つもないと、ボタンは表示されません。
- Content Security Policy を設定しているサイトでは、`script-src` でこのファイルの読み込みを許可してください。CDN から読み込む場合は、CDN のホストを許可します（「[Content Security Policy](#content-security-policy)」を参照）。

### ファイルを直接開いて試すとき（file://） {#file}

HTMLファイルをダブルクリックして開いた場合（アドレスが `file://` で始まる場合）も、ボタンは表示されます。ただし、パソコンの中のファイルの場所はAIに渡しても意味がないため、URLは渡しません。文面は定型文で終わり、URLの部分は空になります。

URLが渡ることを確認するには、次のどちらかを行ってください。

- `data-site-url="https://example.com/"` のように、サイトのURLを指定する。
- 手元のWebサーバーで配信して、`http://localhost:8000/` のようなアドレスで開く。

`localhost` や社内のステージング環境のURLは、AIサービスからは読めません。開発中は、文面とURLが正しく渡っていることだけを確認し、AIがサイトの内容に基づいて答えるかどうかは、公開後に確認してください。
