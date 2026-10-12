# askai-button

A floating "Ask AI" button for any web page. The visitor types a question, picks ChatGPT, Claude or Google AI Mode, and the AI opens in a new tab with the question and the address of your site already filled in.

It calls no API and needs no server. It makes no network requests of its own, and it does not store, send or measure the question.

## Install

The npm package is `@elephancube/askai-button`. The project, its repository and the script file are called askai-button.

```sh
npm install @elephancube/askai-button
```

## Use

With a script tag, using the single file in `dist/` copied to your own server:

```html
<script src="askai-button.min.js" data-askai></script>
```

Or with the same file loaded from a CDN (jsDelivr; unpkg serves it at `https://unpkg.com/@elephancube/askai-button@0.1.0/dist/askai-button.min.js`):

```html
<script src="https://cdn.jsdelivr.net/npm/@elephancube/askai-button@0.1.0/dist/askai-button.min.js" data-askai defer></script>
```

With a CDN, the visitor's browser fetches the script from the CDN operator, a third party; the button itself still sends nothing. Hosting the file yourself avoids that request. Keep the exact version in the address rather than `@latest`, so that a new release cannot change your site unnoticed. See the [install page](https://elephancube.github.io/askai-button/en/install/#cdn) for Subresource Integrity and Content Security Policy.

From code:

```js
import { init } from '@elephancube/askai-button';

init({
  presets: ['What can I do on this site?', 'How much does it cost?'],
  providers: ['chatgpt', 'claude'],
});
```

The package is an ES module with TypeScript declarations; there is no CommonJS build. The script-tag build exposes the same functions on the global `AskAIButton`, for example `AskAIButton.init({ ... })`.

The button follows the language of the page (`<html lang>`). Japanese and English are built in.

The functions are `init`, `open`, `close`, `update`, `registerProvider`, `registerLocale`, `defaults` and `destroy`.

Every option, `data-*` attribute and event is described in the [reference](https://elephancube.github.io/askai-button/en/reference/). Installation steps and a live demo are on the [documentation site](https://elephancube.github.io/askai-button/en/).

## Colours

| Option | Applies to | Notes |
| --- | --- | --- |
| `accentColor` | The launcher button only. | Default `#222`. |
| `color` (on a provider definition) | The selected AI choice and the send link. | Built-in defaults: ChatGPT `#0d7d61`, Claude `#b3542f`, Google AI Mode `#1a73e8`. A provider without a colour uses `#27455c`. Text is always white, so choose a colour dark enough for white text. |

```js
AskAIButton.init({ providers: [{ id: 'claude', color: '#000' }] });
```

## Notes

- The `q` URL parameters used to hand the question to each AI service are undocumented and may change or stop working at any time. Every URL template can be replaced through the options.
- ChatGPT, Claude and Google are trademarks of their respective owners. This project is not affiliated with or endorsed by them.

## License

MIT
