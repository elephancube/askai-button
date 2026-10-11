---
title: リファレンス
description: askai-button の設定と API の一覧です。init() のオプション、data-* 属性、公開している関数、イベント、クエリテンプレートの変数、AIサービスの定義、言語の追加、CSS カスタムプロパティを説明します。
nav: リファレンス
---

このページは、バージョン {{version}} の動作に基づいています。script タグで読み込んだ場合、関数はグローバルオブジェクト `AskAIButton` にあります（例：`AskAIButton.init()`）。npm の場合は、同じ名前の関数を `import` します。

## init() のオプション {#options}

すべて省略できます。使えない値（型が違う、範囲外など）は無視され、既定値が使われます。

| オプション | 型 | 既定値 | 説明 |
| --- | --- | --- | --- |
| `lang` | `string` | `'auto'` | UIの言語。`'auto'` または省略時はページの言語に合わせます（[言語の決まり方](#language)）。 |
| `position` | `'bottom-right' \| 'bottom-left'` | `'bottom-right'` | ボタンを表示する隅。 |
| `offset` | `number` | `24` | 画面の端からの余白（px）。0 以上。 |
| `zIndex` | `number` | `9999` | 重なり順。 |
| `accentColor` | `string` | `'#222'` | 起動ボタンの色。CSS の色として有効な値。文字は白なので、濃い色を指定します。 |
| `providers` | `Array<string \| ProviderDef>` | 登録済みのすべて | 表示するAIサービス。id、または[定義](#providers)の配列。並べた順に表示します。使えるものが1つもないと、ボタンは表示されません。 |
| `defaultProvider` | `string` | 一覧の先頭 | 最初に選んでおくAIサービスの id。訪問者が前回選んだものがあれば、そちらを優先します。 |
| `presets` | `string[]` または言語別 | なし | 質問の例。6件まで。言語別に `{ ja: [...], en: [...] }` とも書けます。 |
| `presetAction` | `'fill' \| 'send'` | `'fill'` | 質問の例を押したときの動作。`'fill'` は入力欄に入れるだけ、`'send'` はすぐにAIサービスを開きます。 |
| `maxLength` | `number` | `500` | 質問の最大文字数。絵文字も1文字として数えます。 |
| `maxUrlLength` | `number` | `8000` | 開くURLの長さの上限。超えると送信できず、「質問が長すぎます」と表示します。 |
| `siteUrl` | `string \| null` | サイトのトップ | `{site_url}` の値。省略時は、ページの言語や階層にかかわらず、表示中のページのオリジン（`https://example.com/`）。複数の言語があるサイトについては「[言語](#language)」を参照。 |
| `url` | `string \| null` | ページのURL | `{url}` の値。省略時は `<link rel="canonical">`、なければ `location.href`。ハッシュと `utm_*` パラメータは取り除きます。 |
| `mdUrl` | `string \| null` | `{url}` と同じ | `{md_url}` の値。ページの Markdown 版のURLがある場合に指定します。 |
| `siteName` | `string \| null` | サイト名 | `{site_name}` の値。省略時は `<meta property="og:site_name">`、なければホスト名。 |
| `queryTemplate` | `string` または言語別、`null` | 言語別の標準の文面 | AIに渡す文面の[テンプレート](#query-template)。 |
| `labels` | `object` | `{}` | [文言](#labels)の上書き。 |
| `onSend` | `(event) => void` | なし | 送信のたびに呼ばれる関数。引数は [`askai:send`](#events) の `detail` と同じです。 |

### 例

```js
AskAIButton.init({
  position: 'bottom-left',
  accentColor: '#27455c',
  providers: ['chatgpt', 'claude'],
  defaultProvider: 'claude',
  presets: {
    ja: ['料金を教えてください', '納期はどのくらいですか？'],
    en: ['How much does it cost?', 'How long does delivery take?'],
  },
  siteUrl: 'https://example.com/shop/',
  onSend: (event) => {
    console.log(event.provider, event.source, event.url);
  },
});
```

## data-* 属性 {#data-attributes}

`data-askai` を付けた script タグでは、次の属性でオプションを指定できます。値はすべて文字列で書きます。

| 属性 | 対応するオプション | 書き方 |
| --- | --- | --- |
| `data-askai` | — | これがあると、読み込み時に自動で `init()` を呼びます。値は不要です。 |
| `data-lang` | `lang` | `ja`、`en` など。 |
| `data-presets` | `presets` | `\|` で区切ります。 |
| `data-position` | `position` | `bottom-right` または `bottom-left`。 |
| `data-offset` | `offset` | 数値。 |
| `data-z-index` | `zIndex` | 数値。 |
| `data-accent-color` | `accentColor` | CSS の色。 |
| `data-providers` | `providers` | id を `,` で区切ります。 |
| `data-default-provider` | `defaultProvider` | id。 |
| `data-preset-action` | `presetAction` | `fill` または `send`。 |
| `data-max-length` | `maxLength` | 数値。 |
| `data-site-url` | `siteUrl` | URL。 |
| `data-url` | `url` | URL。 |
| `data-md-url` | `mdUrl` | URL。 |
| `data-site-name` | `siteName` | 文字列。 |

`maxUrlLength`、`queryTemplate`、`labels`、`onSend`、AIサービスの定義、言語別の `presets` には、対応する属性がありません。これらを使うときは `init()` を呼んでください。

## 関数 {#api}

| 関数 | 説明 |
| --- | --- |
| `init(options)` | ボタンを表示します。もう一度呼ぶと、新しいオプションで作り直します（前回のオプションは引き継ぎません）。 |
| `update(options)` | 一部のオプションだけを変更します。パネルの開閉状態と、入力中の質問は保たれます。 |
| `open()` | パネルを開きます。 |
| `close()` | パネルを閉じます。 |
| `destroy()` | ボタンとパネルを取り除きます。 |
| `registerProvider(def)` | [AIサービスを追加](#providers)、または同じ id の定義を置き換えます。定義が正しくないと `TypeError` を投げます。 |
| `registerLocale(code, dict)` | [言語を追加](#locales)、または既存の言語の文言を置き換えます。 |
| `defaults(lang)` | 標準の文言とAIサービスの定義を返します（読み取り専用）。`lang` を省略するとページの言語です。戻り値は `{ lang, labels, providers }`。 |

```js
// 自分のボタンからパネルを開く
document.querySelector('#ask').addEventListener('click', () => AskAIButton.open());

// 標準の文面を調べる
console.log(AskAIButton.defaults('ja').labels.queryTemplate);
```

## イベント {#events}

`document` に、次の `CustomEvent` を送ります。

| イベント | タイミング | `event.detail` |
| --- | --- | --- |
| `askai:open` | 訪問者がパネルを開いたとき（`open()` を含む）。 | なし |
| `askai:send` | 送信ボタンが押されたとき、または `presetAction: 'send'` で質問の例が押されたとき。 | `{ provider, source, url }` |

| `detail` の項目 | 内容 |
| --- | --- |
| `provider` | 選ばれたAIサービスの id（`chatgpt` など）。 |
| `source` | `'preset'`（質問の例をそのまま送信）または `'free'`（自由入力）。 |
| `url` | 訪問者が見ていたページのURL（`{url}` の値）。 |

**質問の本文は、イベントにも `onSend` にも渡しません。** ボタン自体は、これらの情報をどこにも送りません。アクセス解析に記録するかどうかは、サイト側で決めてください。

```js
document.addEventListener('askai:send', (event) => {
  const { provider, source, url } = event.detail;
  // 例：自分のサイトの計測に記録する
});
```

## クエリテンプレート {#query-template}

AIに渡す文面は、テンプレートの変数を置き換えて作ります。標準のテンプレートは言語ごとに決まっています。

```text
{{query_template}}
```

| 変数 | 置き換わる値 |
| --- | --- |
| `{question}` | 訪問者の質問（前後の空白を除いたもの）。 |
| `{site_url}` | サイトのトップのURL。`siteUrl` オプション、なければ表示中のページのオリジン。 |
| `{url}` | 表示中のページのURL。`url` オプション、`<link rel="canonical">`、`location.href` の順。 |
| `{md_url}` | `mdUrl` オプション。なければ `{url}` と同じ。 |
| `{title}` | ページのタイトル（`document.title`）。 |
| `{site_name}` | サイト名。`siteName` オプション、`<meta property="og:site_name">`、ホスト名の順。 |

これ以外の `{...}` は、そのまま残ります。標準のテンプレートが使うのは `{question}` と `{site_url}` だけです。表示中のページや Markdown 版を渡したい場合は、テンプレートを書き換えます。

```js
AskAIButton.init({
  queryTemplate: {
    ja: '{question}\n\n次のページを参照して回答してください。\n{url}',
    en: '{question}\n\nPlease refer to this page to answer.\n{url}',
  },
});
```

文面は URL エンコードして、AIサービスのURLテンプレートの `{query}` に入れます。できあがったURLが `maxUrlLength` を超えると、送信できません。

## AIサービスの定義 {#providers}

標準では、次の3つが登録されています。

| id | 名称 | URLテンプレート | 色 |
| --- | --- | --- | --- |
| `chatgpt` | ChatGPT | `https://chatgpt.com/?q={query}` | `#0d7d61` |
| `claude` | Claude | `https://claude.ai/new?q={query}` | `#b3542f` |
| `googleai` | Google AIモード（英語では Google AI Mode） | `https://www.google.com/search?udm=50&q={query}` | `#1a73e8` |

これらのURLの形式は、各サービスが公式に公開しているものではなく、予告なく変わることがあります。どの項目も、設定で置き換えられます（「[しくみと注意点]({{page:how-it-works}}#unofficial)」）。

定義の項目は次のとおりです。

| 項目 | 型 | 説明 |
| --- | --- | --- |
| `id` | `string` | 識別子。英数字、`_`、`-`。 |
| `name` | `string` または言語別 | パネルに表示する名称。 |
| `urlTemplate` | `string` | 開くURL。`https://` で始まり、`{query}` を含むこと。 |
| `method` | `'url' \| 'copy_and_open'` | 質問の渡し方。既定は `'url'`（URLに含める）。`'copy_and_open'` は、文面をクリップボードにコピーしてから、`{query}` を空にしたURLを開きます。 |
| `color` | `string` | 選択中のAIサービスと送信ボタンの色。文字は白なので、濃い色を指定します。省略時は `#27455c`。 |

### 既存のAIサービスの一部を変える

`providers` に、id と変えたい項目だけを書きます。

```js
AskAIButton.init({
  providers: [
    'chatgpt',
    { id: 'claude', method: 'copy_and_open' },
    { id: 'googleai', color: '#333' },
  ],
});
```

### AIサービスを追加する

`registerProvider()` で登録すると、`providers` で id を指定できるようになります。`providers` を省略した場合は、登録済みのすべてが登録順に表示されます。

```js
AskAIButton.registerProvider({
  id: 'example',
  name: { ja: 'Example AI', en: 'Example AI' },
  urlTemplate: 'https://ai.example.com/search?q={query}',
  color: '#444',
});

AskAIButton.init({
  providers: ['chatgpt', 'claude', 'googleai', 'example'],
  labels: { 'note.example': '質問は自動で送信されます。' },
});
```

`ai.example.com` は説明用の架空のアドレスです。実際に追加するときは、そのサービスが質問をURLで受け取れるかどうかを確認してください。

## 文言 {#labels}

`labels` で、ボタンとパネルの文言を上書きできます。キーは次のとおりです。

| キー | 日本語の標準の文言 |
| --- | --- |
| `buttonLabel` | AIに質問 |
| `panelTitle` | このサイトについてAIに聞く |
| `panelSubtitle` | 選んだAIサービスが別タブで開きます。回答はAIサービスが生成します。 |
| `inputLabel` | 質問（読み上げ用） |
| `placeholder` | 質問を入力してください |
| `presetsLabel` | 質問の例（読み上げ用） |
| `providersLabel` | 使うAI（読み上げ用） |
| `note` | このサイトのURLだけを渡します。{provider} はURLをその場で開いて読みます。 |
| `note.chatgpt` | 質問は自動で送信されます。 |
| `note.claude` | 質問が入力された状態で開きます。送信はご自身で押してください（ログインが必要です）。 |
| `note.googleai` | Google 検索の AIモードで開きます。AIモードを使えない地域・アカウントでは、通常の検索結果になることがあります。 |
| `fallbackTitle` | AIがこのサイトを読めない場合 |
| `fallbackSteps` | 質問とURLをコピーし、AIの入力欄に貼り付けて送信してください。 |
| `copy` | 質問とURLをコピー |
| `copied` | コピーしました |
| `copyHint` | 質問をコピーしました。{provider} の入力欄に貼り付けて送信してください。 |
| `send` | {provider} で質問する ↗ |
| `close` | 閉じる |
| `tooLong` | 質問が長すぎます |
| `queryTemplate` | [クエリテンプレート](#query-template) |

`{provider}` は、選択中のAIサービスの名称に置き換わります。`note.<id>` は、そのAIサービスを選んだときに `note` の後ろに続けて表示する注記です。

全言語共通で指定することも、言語別に指定することもできます。

```js
AskAIButton.init({
  labels: {
    // すべての言語で使う
    placeholder: '例：送料はいくらですか？',
    // 言語別
    ja: { buttonLabel: 'AIに聞いてみる' },
    en: { buttonLabel: 'Ask an AI' },
  },
});
```

## 言語 {#language}

UIの言語は、閲覧者のブラウザーの設定ではなく、ページの言語に合わせます。次の順で決まります。

1. `lang` オプション（`'auto'` 以外が指定されている場合）
2. `<html lang>`
3. ブラウザーの言語（`navigator.language`）
4. 英語

`pt-BR` のような地域付きの言語は、`pt-BR`、`pt` の順に辞書を探し、なければ英語で表示します。同梱している辞書は日本語（`ja`）と英語（`en`）です。

言語別に書いた `presets`、`queryTemplate`、AIサービスの `name` は、ページの言語、その基本の言語、`en` の順に探します。言語別の `labels` は、ページの言語に一致するものを使い、UIが英語で表示されるときは `en` の指定も使います。

言語によって、AIに渡すURLは変わりません。`{site_url}` は、言語ごとに `/en/` のようなフォルダーに分けているサイトでも、どの言語のページでもオリジン（`https://example.com/`）です。言語ごとのトップページを渡したい場合は、その言語のページで `data-site-url`（`init()` では `siteUrl`）を指定します。例：`data-site-url="https://example.com/en/"`。

### 言語を追加する {#locales}

`registerLocale()` に、言語コードと文言を渡します。指定しなかったキーは英語で表示されます。

```js
AskAIButton.registerLocale('fr', {
  buttonLabel: "Demander à l'IA",
  panelTitle: "Interroger l'IA sur ce site",
  placeholder: 'Saisissez votre question',
  send: 'Demander à {provider} ↗',
  close: 'Fermer',
  queryTemplate: "{question}\n\nVeuillez vous référer à l'URL suivante pour répondre à cette question.\n{site_url}",
});

AskAIButton.init();
```

## CSS カスタムプロパティ {#css}

ボタンとパネルは Shadow DOM の中にあるため、ページのCSSの影響を受けません。見た目のうち、次のものはCSSカスタムプロパティで変えられます。`:root` などに指定してください。

| プロパティ | 既定値 | 内容 |
| --- | --- | --- |
| `--askai-accent` | `#222` | 起動ボタンの色。 |
| `--askai-accent-text` | `#fff` | 起動ボタンの文字とアイコンの色。 |
| `--askai-select` | `#27455c` | フォーカスの枠の色。`color` のないAIサービスの、選択中の色と送信ボタンの色。 |
| `--askai-offset` | `24px` | 画面の端からの余白。 |
| `--askai-z-index` | `9999` | 重なり順。 |
| `--askai-panel-width` | `360px` | パネルの幅。画面の幅が 480px 未満のときは、左右に 16px を残した全幅になります。 |

```css
:root {
  --askai-accent: #27455c;
  --askai-panel-width: 400px;
}
```

`accentColor`、`offset`、`zIndex` オプションを指定した場合は、オプションの値が優先されます。

## ページごとに非表示にする {#opt-out}

次の meta タグがあるページでは、`init()` を呼んでもボタンを表示しません。共通のテンプレートで読み込んでいて、一部のページだけ外したいときに使います。

```html
<meta name="askai" content="off">
```

## SPA での動作 {#spa}

- ページのURLとタイトルは、送信のたびにその時点のものを読み取ります。画面遷移のたびに `init()` を呼び直す必要はありません。
- 画面ごとに質問の例などを変えたいときは、`update()` を使います。パネルが開いていても、入力中の質問は消えません。
- `<meta name="askai" content="off">` を確認するのは、`init()`、`update()`、`registerProvider()`、`registerLocale()` を呼んだときです。meta タグの変化は監視しません。
- ボタンは `document.body` の直下に追加されます。不要になったら `destroy()` で取り除きます。

## 対応ブラウザー {#browsers}

配布ファイルは ES2019 の構文で出力しています。Chrome、Edge、Firefox、Safari の最新2バージョンと、iOS の Safari、Android の Chrome を対象にしています。

## Content Security Policy {#csp}

- スタイルは、構築済みスタイルシート（`adoptedStyleSheets`）で Shadow DOM に適用します。現在のブラウザーでは `<style>` 要素を使わないため、`style-src` に `'unsafe-inline'` を追加する必要はありません。構築済みスタイルシートに対応していないブラウザーでは、Shadow DOM の中に `<style>` 要素を作ります。
- `script-src` では、`askai-button.min.js` を置いた場所を許可してください。`data-askai` を使えば、インラインスクリプトは不要です。
- ボタンは外部と通信しないため、`connect-src` の設定は不要です。
