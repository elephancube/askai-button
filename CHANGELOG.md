# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Changed

- WordPress plugin: renamed to Elephancube Ask AI Button (slug and text domain `elephancube-ask-ai-button`, folder `wordpress/elephancube-ask-ai-button`). The settings, post meta and usage counts keep their names, so an install of the 0.1.0 zip from GitHub keeps its settings; the new plugin does not run while the old copy is active. The settings screen is now at `options-general.php?page=elephancube-ask-ai-button` and the usage-count endpoint at `elephancube-ask-ai-button/v1/count`.

### Fixed

- WordPress plugin: uninstalling and "Reset counts" delete only options whose whole name has the plugin's own form (`askai_count_<YYYY-MM>_open` or `askai_count_<YYYY-MM>_send_<id>`), not every option that starts with `askai_count_`.

## [0.1.0] - 2026-10-12

### Added

- Core library (`packages/core`, MIT): a floating "Ask AI" button and panel in a Shadow DOM, with no runtime dependencies. The visitor's question is handed to ChatGPT, Claude or Google AI Mode in a new tab through a configurable URL template; a service can instead be set to copy the text to the clipboard and open the service.
- Options for position, offset, z-index, accent colour, provider list and default provider, preset questions (fill or send), maximum question length, site URL, page URL and site name, query template, labels, and an `onSend` callback. Provider names and colours can be overridden or new providers registered.
- Script-tag use with `data-askai` and `data-*` attributes (global `AskAIButton`). The single file can be hosted on your own server or loaded from a CDN (jsDelivr, unpkg).
- npm package `@elephancube/askai-button`: an ES module with TypeScript declarations (no CommonJS build). It also contains the single file for the script tag.
- Programmatic functions: `init`, `open`, `close`, `update`, `registerProvider`, `registerLocale`, `defaults`, `destroy`.
- Built-in Japanese and English wording, chosen from the page language; custom languages through `registerLocale`.
- A `<meta name="askai" content="off">` switch to hide the button on a page.
- WordPress plugin (`wordpress/askai-button`, GPL-2.0-or-later): a settings screen under Settings > Ask AI (display, texts, preset questions, AI services, query template), a live preview, and a per-post box to hide the button or set page-specific questions.
- Polylang and WPML (String Translation) support for the texts entered on the settings screen.
- On sites run with Polylang or WPML, the address handed to the AI service is the top URL of the site (the front page of the default language) on every page; a setting switches it to the front page of the language being viewed.
- Plugin settings screen translated into Japanese; `npm run i18n` to regenerate translation files.
- WordPress plugin: optional usage counts, off by default. Monthly totals of how often the button opened the panel and how often a question was sent to each AI service, kept in the site's own database and shown under Settings > Ask AI, with a CSV download and a reset. The question, the page and anything about the visitor are never stored.
- Filters `askai_should_display`, `askai_presets`, `askai_providers`, `askai_query_template` and `askai_site_url`.
- Documentation and demo website (Japanese and English), plain HTML examples, and a script that packs the plugin into a zip.
