---
title: Reference
description: The settings and the API of askai-button - the options of init(), the data-* attributes, the public functions, the events, the variables of the query template, the definitions of the AI services, adding languages, and the CSS custom properties.
nav: Reference
---

This page describes version {{version}}. When the file is loaded with a script tag, the functions are on the global object `AskAIButton` (for example `AskAIButton.init()`). With npm, you `import` functions of the same names.

## Options of init() {#options}

Every option can be left out. A value that cannot be used (wrong type, out of range and so on) is ignored and the default applies.

| Option | Type | Default | Meaning |
| --- | --- | --- | --- |
| `lang` | `string` | `'auto'` | Language of the UI. With `'auto'`, or when left out, it follows the language of the page (see [Language](#language)). |
| `position` | `'bottom-right' \| 'bottom-left'` | `'bottom-right'` | The corner in which the button is shown. |
| `offset` | `number` | `24` | Distance from the edges of the screen, in px. Zero or more. |
| `zIndex` | `number` | `9999` | Stacking order. |
| `accentColor` | `string` | `'#222'` | Colour of the launcher button. Any valid CSS colour. The text is white, so choose a dark colour. |
| `providers` | `Array<string \| ProviderDef>` | all registered services | The AI services to offer: ids or [definitions](#providers), shown in the order given. If none of them is usable, the button is not shown. |
| `defaultProvider` | `string` | the first in the list | The id of the service selected at first. A service the visitor chose before takes precedence. |
| `presets` | `string[]`, or one list per language | none | Example questions, up to six. Can be written per language as `{ ja: [...], en: [...] }`. |
| `presetAction` | `'fill' \| 'send'` | `'fill'` | What a press on an example question does. `'fill'` only puts it into the question field; `'send'` opens the AI service at once. |
| `maxLength` | `number` | `500` | Maximum length of a question, in characters. An emoji counts as one character. |
| `maxUrlLength` | `number` | `8000` | Longest address that may be opened. Beyond it, sending is disabled and "Your question is too long." is shown. |
| `siteUrl` | `string \| null` | the top of the site | Value of `{site_url}`. When left out: the origin of the current page (`https://example.com/`). |
| `url` | `string \| null` | the address of the page | Value of `{url}`. When left out: `<link rel="canonical">`, then `location.href`. The hash and `utm_*` parameters are removed. |
| `mdUrl` | `string \| null` | same as `{url}` | Value of `{md_url}`. Set it when the page has a Markdown version. |
| `siteName` | `string \| null` | the name of the site | Value of `{site_name}`. When left out: `<meta property="og:site_name">`, then the host name. |
| `queryTemplate` | `string`, or one per language, or `null` | the built-in text of the language | The [template](#query-template) of the text handed to the AI. |
| `labels` | `object` | `{}` | Replacements for the [wording](#labels). |
| `onSend` | `(event) => void` | none | Called on every send. The argument is the same as `detail` of [`askai:send`](#events). |

### Example

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

## data-* attributes {#data-attributes}

On a script tag with `data-askai`, these attributes set the options. All values are written as text.

| Attribute | Option | How to write it |
| --- | --- | --- |
| `data-askai` | — | Makes the file call `init()` by itself when it is loaded. Needs no value. |
| `data-lang` | `lang` | `ja`, `en` and so on. |
| `data-presets` | `presets` | Separated by `\|`. |
| `data-position` | `position` | `bottom-right` or `bottom-left`. |
| `data-offset` | `offset` | A number. |
| `data-z-index` | `zIndex` | A number. |
| `data-accent-color` | `accentColor` | A CSS colour. |
| `data-providers` | `providers` | Ids separated by `,`. |
| `data-default-provider` | `defaultProvider` | An id. |
| `data-preset-action` | `presetAction` | `fill` or `send`. |
| `data-max-length` | `maxLength` | A number. |
| `data-site-url` | `siteUrl` | A URL. |
| `data-url` | `url` | A URL. |
| `data-md-url` | `mdUrl` | A URL. |
| `data-site-name` | `siteName` | Text. |

There is no attribute for `maxUrlLength`, `queryTemplate`, `labels`, `onSend`, definitions of AI services, or `presets` per language. Call `init()` to use those.

## Functions {#api}

| Function | Meaning |
| --- | --- |
| `init(options)` | Shows the button. Calling it again rebuilds the button with the new options (the earlier options are not kept). |
| `update(options)` | Changes some options only. The panel stays open or closed as it was, and the question being typed is kept. |
| `open()` | Opens the panel. |
| `close()` | Closes the panel. |
| `destroy()` | Removes the button and the panel. |
| `registerProvider(def)` | [Adds an AI service](#providers), or replaces the definition with the same id. Throws a `TypeError` when the definition is not valid. |
| `registerLocale(code, dict)` | [Adds a language](#locales), or replaces wording of an existing one. |
| `defaults(lang)` | Returns the built-in wording and the definitions of the AI services (read-only). Without `lang`, for the language of the page. The result is `{ lang, labels, providers }`. |

```js
// Open the panel from a button of your own
document.querySelector('#ask').addEventListener('click', () => AskAIButton.open());

// Look up the built-in text
console.log(AskAIButton.defaults('en').labels.queryTemplate);
```

## Events {#events}

These events (each a `CustomEvent`) are dispatched on `document`.

| Event | When | `event.detail` |
| --- | --- | --- |
| `askai:open` | The visitor opened the panel (including through `open()`). | none |
| `askai:send` | The send button was pressed, or an example question was pressed with `presetAction: 'send'`. | `{ provider, source, url }` |

| Field of `detail` | Meaning |
| --- | --- |
| `provider` | The id of the chosen AI service, such as `chatgpt`. |
| `source` | `'preset'` (an example question sent as it is) or `'free'` (typed by the visitor). |
| `url` | The address of the page the visitor was on (the value of `{url}`). |

**The question itself is never passed, neither to the events nor to `onSend`.** The button sends this information nowhere. Whether to record it in your analytics is your decision.

```js
document.addEventListener('askai:send', (event) => {
  const { provider, source, url } = event.detail;
  // for example: record it in the statistics of your own site
});
```

## Query template {#query-template}

The text handed to the AI is made by replacing the variables of a template. Each language has its own built-in template.

```text
{{query_template}}
```

| Variable | Replaced by |
| --- | --- |
| `{question}` | The visitor's question, without leading and trailing whitespace. |
| `{site_url}` | The address of the top of the site: the `siteUrl` option, otherwise the origin of the current page. |
| `{url}` | The address of the current page: the `url` option, then `<link rel="canonical">`, then `location.href`. |
| `{md_url}` | The `mdUrl` option, otherwise the same as `{url}`. |
| `{title}` | The title of the page (`document.title`). |
| `{site_name}` | The name of the site: the `siteName` option, then `<meta property="og:site_name">`, then the host name. |

Any other `{...}` is left as it is. The built-in templates use `{question}` and `{site_url}` only. To hand over the current page or its Markdown version, write your own template.

```js
AskAIButton.init({
  queryTemplate: {
    ja: '{question}\n\n次のページを参照して回答してください。\n{url}',
    en: '{question}\n\nPlease refer to this page to answer.\n{url}',
  },
});
```

The text is URL-encoded and put into the place of `{query}` in the URL template of the AI service. If the resulting address is longer than `maxUrlLength`, it cannot be sent.

## Definitions of AI services {#providers}

Three services are registered from the start.

| id | Name | URL template | Colour |
| --- | --- | --- | --- |
| `chatgpt` | ChatGPT | `https://chatgpt.com/?q={query}` | `#0d7d61` |
| `claude` | Claude | `https://claude.ai/new?q={query}` | `#b3542f` |
| `googleai` | Google AI Mode (Google AIモード in Japanese) | `https://www.google.com/search?udm=50&q={query}` | `#1a73e8` |

These URL formats are not documented by the services and can change without notice. Every field can be replaced through the settings (see "[How it works and caveats]({{page:how-it-works}}#unofficial)").

A definition has these fields:

| Field | Type | Meaning |
| --- | --- | --- |
| `id` | `string` | Identifier: letters, digits, `_` and `-`. |
| `name` | `string`, or one per language | The name shown in the panel. |
| `urlTemplate` | `string` | The address to open. Must start with `https://` and contain `{query}`. |
| `method` | `'url' \| 'copy_and_open'` | How the question is handed over. The default `'url'` puts it into the address. `'copy_and_open'` copies the text to the clipboard and then opens the address with `{query}` left empty. |
| `color` | `string` | Colour of the selected service and of the send button. The text is white, so choose a dark colour. Without it, `#27455c` is used. |

### Changing part of a built-in service

In `providers`, write the id and only the fields you want to change.

```js
AskAIButton.init({
  providers: [
    'chatgpt',
    { id: 'claude', method: 'copy_and_open' },
    { id: 'googleai', color: '#333' },
  ],
});
```

### Adding an AI service

A service registered with `registerProvider()` can be named by its id in `providers`. When `providers` is left out, all registered services are shown in the order they were registered.

```js
AskAIButton.registerProvider({
  id: 'example',
  name: { ja: 'Example AI', en: 'Example AI' },
  urlTemplate: 'https://ai.example.com/search?q={query}',
  color: '#444',
});

AskAIButton.init({
  providers: ['chatgpt', 'claude', 'googleai', 'example'],
  labels: { 'note.example': 'Your question is sent automatically.' },
});
```

`ai.example.com` is a made-up address for this explanation. Before adding a real service, check whether it accepts a question in its URL.

## Wording {#labels}

`labels` replaces the wording of the button and the panel. These are the keys:

| Key | Built-in English wording |
| --- | --- |
| `buttonLabel` | Ask AI |
| `panelTitle` | Ask AI about this site |
| `panelSubtitle` | Your question opens in the AI service you choose. The answer is generated by that service. |
| `inputLabel` | Your question (for screen readers) |
| `placeholder` | Type your question |
| `presetsLabel` | Example questions (for screen readers) |
| `providersLabel` | AI service (for screen readers) |
| `note` | Only the URL of this site is passed. {provider} opens the URL and reads it on the spot. |
| `note.chatgpt` | Your question is sent automatically. |
| `note.claude` | Your question is filled in for you. Press send yourself. Sign-in is required. |
| `note.googleai` | Opens Google Search in AI Mode. Where AI Mode is unavailable you may see regular search results. |
| `fallbackTitle` | If the AI cannot read this site |
| `fallbackSteps` | Copy the question and URL, then paste them into the AI and send. |
| `copy` | Copy question and URL |
| `copied` | Copied |
| `copyHint` | Question copied. Paste it into {provider} and send. |
| `send` | Ask {provider} ↗ |
| `close` | Close |
| `tooLong` | Your question is too long. |
| `queryTemplate` | The [query template](#query-template) |

`{provider}` is replaced by the name of the selected AI service. `note.<id>` is a remark shown after `note` while that service is selected.

Wording can be given for all languages at once, or per language.

```js
AskAIButton.init({
  labels: {
    // used in every language
    placeholder: 'e.g. How much is shipping?',
    // per language
    ja: { buttonLabel: 'AIに聞いてみる' },
    en: { buttonLabel: 'Ask an AI' },
  },
});
```

## Language {#language}

The language of the UI follows the page, not the settings of the visitor's browser. It is decided in this order:

1. the `lang` option, when it is set to something other than `'auto'`
2. `<html lang>`
3. the language of the browser (`navigator.language`)
4. English

For a language with a region, such as `pt-BR`, a dictionary is looked for under `pt-BR`, then `pt`; without one the UI is in English. The built-in dictionaries are Japanese (`ja`) and English (`en`).

`presets`, `queryTemplate` and the `name` of an AI service, when written per language, are looked up under the language of the page, then its base language, then `en`. Per-language `labels` are used when they match the language of the page; when the UI is shown in English, those under `en` are used as well.

### Adding a language {#locales}

Pass a language code and the wording to `registerLocale()`. Keys you leave out are shown in English.

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

## CSS custom properties {#css}

The button and the panel live in a shadow DOM, so the CSS of the page does not affect them. These parts of their look can be changed with CSS custom properties. Set them on `:root`, for example.

| Property | Default | Meaning |
| --- | --- | --- |
| `--askai-accent` | `#222` | Colour of the launcher button. |
| `--askai-accent-text` | `#fff` | Colour of the text and the icon of the launcher button. |
| `--askai-select` | `#27455c` | Colour of the focus ring, and of the selected service and the send button for a service without a `color`. |
| `--askai-offset` | `24px` | Distance from the edges of the screen. |
| `--askai-z-index` | `9999` | Stacking order. |
| `--askai-panel-width` | `360px` | Width of the panel. On screens narrower than 480px the panel takes the full width, leaving 16px on each side. |

```css
:root {
  --askai-accent: #27455c;
  --askai-panel-width: 400px;
}
```

When the options `accentColor`, `offset` or `zIndex` are given, the options win.

## Hiding the button on a page {#opt-out}

A page with this meta tag does not show the button, even when `init()` is called. Use it when the script is loaded from a shared template and some pages should be left out.

```html
<meta name="askai" content="off">
```

## Single-page applications {#spa}

- The address and the title of the page are read at the moment of sending. There is no need to call `init()` again after a navigation.
- To change the example questions or other options from one view to the next, use `update()`. The question being typed is kept, also while the panel is open.
- `<meta name="askai" content="off">` is checked when `init()`, `update()`, `registerProvider()` or `registerLocale()` is called. Changes of the meta tag are not watched.
- The button is added directly under `document.body`. Remove it with `destroy()` when it is no longer needed.

## Browser support {#browsers}

The distributed files use ES2019 syntax. The targets are the last two versions of Chrome, Edge, Firefox and Safari, plus Safari on iOS and Chrome on Android.

## Content Security Policy {#csp}

- The styles are applied to the shadow DOM with a constructed stylesheet (`adoptedStyleSheets`). Current browsers need no `<style>` element for this, so `'unsafe-inline'` does not have to be added to `style-src`. In a browser without constructed stylesheets, a `<style>` element is created inside the shadow DOM.
- In `script-src`, allow the place where `askai-button.min.js` is served from. With `data-askai`, no inline script is needed.
- The button makes no network requests, so nothing has to be set in `connect-src`.
