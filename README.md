# askai-button

A floating "Ask AI" button for any web page. The visitor types a question, picks ChatGPT, Claude or Google AI Mode, and the AI opens in a new tab with the question and the address of your site already filled in.

It calls no API and needs no server. It makes no network requests, and it does not store, send or measure the question.

## Install

```html
<script src="askai-button.min.js" data-askai></script>
```

The button follows the language of the page (`<html lang>`). Japanese and English are built in.

Options can be given as `data-*` attributes on the script tag, or from code:

```js
AskAIButton.init({
  presets: ['What can I do on this site?', 'How much does it cost?'],
  providers: ['chatgpt', 'claude'],
});
```

See [`examples/`](examples/) for complete pages.

## Colours

| Option | Applies to | Notes |
| --- | --- | --- |
| `accentColor` | The launcher button only. | Default `#222`. |
| `color` (on a provider definition) | The selected AI choice and the send link. | Built-in defaults: ChatGPT `#0d7d61`, Claude `#b3542f`, Google AI Mode `#1a73e8`. A provider without a colour uses `#27455c`. Text is always white, so choose a colour dark enough for white text. |

```js
AskAIButton.init({ providers: [{ id: 'claude', color: '#000' }] });
```

## WordPress plugin

[`wordpress/askai-button/`](wordpress/askai-button/) is a WordPress plugin that adds the button to a site and lets you set it up on a settings screen. It is a thin layer around the same script; see its [`readme.txt`](wordpress/askai-button/readme.txt).

The script it loads is a build artefact and is not committed. To get an installable plugin:

```sh
npm install
npm run build        # builds the script and copies it into the plugin
npm run zip:plugin   # writes dist-plugin/askai-button-<version>.zip
```

Upload the zip under Plugins > Add New Plugin > Upload Plugin. The plugin is licensed under GPL-2.0-or-later.

## Development

```sh
npm install
npm test
npm run build
```

## Notes

- The `q` URL parameters used to hand the question to each AI service are undocumented and may change or stop working at any time. Every URL template can be replaced through the options.
- ChatGPT, Claude and Google are trademarks of their respective owners. This project is not affiliated with or endorsed by them.

## License

MIT. See [LICENSE](LICENSE).
