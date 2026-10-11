# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Added

- Core library (`packages/core`, MIT): a floating "Ask AI" button and panel in a Shadow DOM, with no runtime dependencies. The visitor's question is handed to ChatGPT, Claude or Google AI Mode in a new tab through a configurable URL template; a service can instead be set to copy the text to the clipboard and open the service.
- Options for position, offset, z-index, accent colour, provider list and default provider, preset questions (fill or send), maximum question length, site URL, page URL and site name, query template, labels, and an `onSend` callback. Provider names and colours can be overridden or new providers registered.
- Script-tag use with `data-askai` and `data-*` attributes (global `AskAIButton`), and an ESM build for npm.
- Programmatic functions: `init`, `open`, `close`, `update`, `registerProvider`, `registerLocale`, `defaults`, `destroy`.
- Built-in Japanese and English wording, chosen from the page language; custom languages through `registerLocale`.
- A `<meta name="askai" content="off">` switch to hide the button on a page.
- WordPress plugin (`wordpress/askai-button`, GPL-2.0-or-later): a settings screen under Settings > Ask AI (display, texts, preset questions, AI services, query template), a live preview, and a per-post box to hide the button or set page-specific questions.
- Polylang and WPML (String Translation) support for the texts entered on the settings screen.
- Plugin settings screen translated into Japanese; `npm run i18n` to regenerate translation files.
- Filters `askai_should_display`, `askai_presets`, `askai_providers`, `askai_query_template` and `askai_site_url`.
- Documentation and demo website (Japanese and English), plain HTML examples, and a script that packs the plugin into a zip.
