# askai-button

English | [日本語](README.ja.md)

A floating "Ask AI" button for any web page. The visitor types a question, picks ChatGPT, Claude or Google AI Mode, and that service opens in a new tab with the question and the address of your site already filled in. No AI API is called, and the widget itself stores and sends nothing.

One TypeScript source produces a single-file script (global `AskAIButton`), an npm ESM package and a thin WordPress plugin.

Documentation and a live demo: https://elephancube.github.io/askai-button/en/ ([install](https://elephancube.github.io/askai-button/en/install/), [option reference](https://elephancube.github.io/askai-button/en/reference/), [WordPress](https://elephancube.github.io/askai-button/en/wordpress/))

## What is handed to the AI

The text placed in the URL parameter of the chosen service is built from a template. The built-in English template is:

```
{question}

Please refer to the following URL to answer this question.
{site_url}
```

`{question}` is what the visitor typed. `{site_url}` is the root of the current origin unless you set `siteUrl`. The template, and the wording of every label, can be replaced through the options.

## Quick start

With a script tag, the file being on your own server (download `askai-button.min.js` from the [releases](https://github.com/elephancube/askai-button/releases) or the documentation site):

```html
<script src="askai-button.min.js" data-askai></script>
```

With a script tag, the file coming from a CDN (jsDelivr; unpkg serves the same file at `https://unpkg.com/askai-button@0.1.0/dist/askai-button.min.js`):

```html
<script src="https://cdn.jsdelivr.net/npm/askai-button@0.1.0/dist/askai-button.min.js" data-askai defer></script>
```

With a CDN, the visitor's browser fetches the script from the CDN operator, a third party; the button itself still sends nothing. Hosting the file yourself avoids that request. Keep the exact version in the address: an address without a version, or with `@latest`, changes the script on your site whenever a release is published. Subresource Integrity and Content Security Policy are covered on the [install page](https://elephancube.github.io/askai-button/en/install/#cdn).

With npm:

```sh
npm install askai-button
```

```js
import { init } from 'askai-button';

init({
  presets: ['What can I do on this site?', 'How much does it cost?'],
  providers: ['chatgpt', 'claude'],
});
```

The button follows the page language (`lang` option, then `<html lang>`, then the browser language, then `en`). Japanese and English are built in. See [`examples/`](examples/) for complete pages.

## WordPress

The plugin is in [`wordpress/askai-button/`](wordpress/askai-button/). It adds the button and a settings screen (Settings > Ask AI). Installation and details: see its [`readme.txt`](wordpress/askai-button/readme.txt) and the [WordPress page](https://elephancube.github.io/askai-button/en/wordpress/) of the documentation site.

## What it does not do

- It calls no AI API and needs no API key.
- It runs no server and makes no network requests of its own. (Loading the script file is a request to wherever you host it: your own server, or a CDN if you choose one.)
- It does not track, measure or store the question, and sets no cookies.
- The only thing kept is the visitor's last chosen service, in `localStorage`.

## Caveats

- The `q` URL parameters these services accept are not documented by them and may change or stop working at any time. Every URL template is configurable, and a service can be set to copy the text to the clipboard and open the service instead.
- The AI service has to be able to fetch your site. A site that blocks AI crawlers, needs a login or is not reachable from the internet cannot be read. The panel offers a button that copies the question and URL for pasting by hand.
- Nothing here guarantees that a given service accepts the URL. Check the services you offer yourself.

## Repository layout

- `packages/core/` - the library (TypeScript, no runtime dependencies). All front-end behaviour lives here.
- `wordpress/askai-button/` - the WordPress plugin; it passes settings to the library.
- `site/` - the documentation and demo site: Markdown pages in `site/content/`, built into `site/dist/` by `site/build.mjs`.
- `examples/` - plain HTML examples (run the build first).
- `scripts/` - build helpers.
- `.github/workflows/` - CI (`ci.yml`) and the deployment of the site to GitHub Pages (`pages.yml`).

## Development

```sh
npm install
npm test              # unit tests (Vitest)
npm run typecheck
npm run build         # builds the script, copies it into the plugin, builds the site
npm run build:site    # builds the site only (into site/dist)
npm run site          # builds everything, then serves the site locally
npm run serve:site    # serves the site that is already built
npm run test:php      # plugin tests, needs PHP
npm run i18n          # regenerates plugin translation files
npm run zip:plugin    # writes dist-plugin/askai-button-<version>.zip
```

See [CONTRIBUTING.md](CONTRIBUTING.md). To report a security problem, see [SECURITY.md](SECURITY.md).

## License

- The core library (everything outside `wordpress/`) is MIT licensed; see [LICENSE](LICENSE).
- The WordPress plugin in `wordpress/askai-button/` is GPL-2.0-or-later; see its [LICENSE](wordpress/askai-button/LICENSE).

Copyright (c) 2026 Elephancube Inc. Free and open source; there is no paid version. Support is best effort.

## Trademarks

ChatGPT, Claude and Google are trademarks of their respective owners. This project is not affiliated with or endorsed by them. No logos of these services are bundled.
