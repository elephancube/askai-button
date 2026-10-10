# askai-button

A floating "Ask AI" button for any web page. The visitor types a question, picks ChatGPT, Claude or Google AI Mode, and the AI opens in a new tab with the question and the address of your site already filled in.

It calls no API and needs no server. It makes no network requests, and it does not store, send or measure the question.

## Install

```sh
npm install askai-button
```

## Use

With a script tag, using the single file in `dist/`:

```html
<script src="askai-button.min.js" data-askai></script>
```

From code:

```js
import { init } from 'askai-button';

init({
  presets: ['What can I do on this site?', 'How much does it cost?'],
  providers: ['chatgpt', 'claude'],
});
```

The script-tag build exposes the same functions on the global `AskAIButton`, for example `AskAIButton.init({ ... })`.

The button follows the language of the page (`<html lang>`). Japanese and English are built in.

For the options and complete example pages, see the repository README.

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
