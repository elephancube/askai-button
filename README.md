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
