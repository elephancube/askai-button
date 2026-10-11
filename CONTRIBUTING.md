# Contributing

Thank you for considering a contribution. This is a small, free and open-source project. Support is best effort: there is no service level, and replies may be slow.

## What is welcome

- Bug reports.
- Reports that a service changed its URL format. Please include the service, the date you noticed, the browser and device, and what happened (use the "Service URL change" issue form).
- Translations (see below).
- Small fixes and documentation corrections.

For larger changes, open an issue first so we can agree on the direction before you spend time on it.

## What is out of scope

- Calling AI APIs.
- Storing or analysing questions.
- Bundling service logos.
- Adding runtime dependencies.
- Anything that sends data anywhere.

## Development setup

```sh
npm install
npm test              # unit tests (Vitest)
npm run typecheck
npm run build         # builds the script, checks its size, copies it into the plugin
npm run test:php      # plugin tests; needs PHP on your PATH
npm run i18n          # regenerates the plugin translation files
npm run zip:plugin    # writes dist-plugin/askai-button-<version>.zip (run the build first)
```

## Rules for pull requests

- All front-end behaviour (UI, query building, language detection) lives in `packages/core`. The WordPress plugin only passes configuration to it; do not re-implement logic in PHP or in the plugin's JavaScript.
- UI strings live only in the locale dictionaries in `packages/core/src/locales/`. Every language must have exactly the same keys as `en.ts`.
- Never put text into the page with `innerHTML`. Text goes in through `textContent`, and the question goes into URLs through `encodeURIComponent`.
- URL templates are configuration. Never assume a service's URL parameter works.
- No runtime dependencies. The built script must stay under 15 KB gzip; `npm run build` fails if it does not.
- `wordpress/askai-button/assets/askai-button.min.js` is a build artefact (it is git-ignored). Do not edit or commit it.
- PHP must be valid for PHP 7.4. Sanitize input and escape output.
- CSS lives inside the Shadow DOM and uses logical properties. Do not give buttons or chips a fixed width.

### Adding a language

There are two ways to provide a dictionary.

1. Built in: add `packages/core/src/locales/<code>.ts`, exporting a `Dict` (the type from `en.ts`) with every key of `en.ts`, then register it in the `locales` map in `packages/core/src/i18n.ts` next to `en` and `ja`. Add or update tests.
2. At runtime, without touching the library: call `registerLocale('<code>', { ... })` with some or all of the keys. Missing keys fall back to English. Codes are matched case-insensitively, and `pt-BR` falls back to `pt`.

Keep placeholders such as `{provider}`, `{question}` and `{site_url}` intact in translated strings. Built-in dictionaries must keep the same keys as English, including the per-service `note.*` keys.

### WordPress plugin translations

The plugin's own screens are translated with gettext files in `wordpress/askai-button/languages/`. Run `npm run i18n` after changing translatable strings in the PHP sources or a `.po` file. It rewrites the `.pot` and `.po` files and compiles the `.mo` files, and exits with an error if a `.po` file is missing a translation, has an unused one, or has placeholders that differ from the original.

### Tests

`npm test` runs the core tests in `packages/core/test/`. `npm run test:php` runs `wordpress/askai-button/tests/run.php`.

## Commits and pull requests

Keep them small and focused, with a short description of what changed and why. Mention how you tested it. No special commit format is required.

## Licence of contributions

By submitting a contribution you agree that it is licensed under the licence of the part of the project it changes: MIT for the core library and everything outside `wordpress/`, GPL-2.0-or-later for the WordPress plugin in `wordpress/askai-button/`.

## 日本語の要約

- サポートはベストエフォートです。返信が遅れることがあります。
- 歓迎するもの: バグ報告、サービスのURL形式が変わったという報告（サービス名、気づいた日、ブラウザと端末、起きたことを書いてください）、翻訳、小さな修正。
- 対象外: AIのAPI呼び出し、質問の保存・分析、サービスのロゴの同梱、ランタイム依存の追加、データをどこかへ送る機能。
- フロントの挙動はすべて `packages/core` に置き、WordPressプラグインは設定を渡すだけにします。UI文言は `locales` の辞書だけに置き、キーは全言語で揃えます。`innerHTML` は使わず `textContent` を使います。URLテンプレートは設定値で、動作を前提にしません。配布JSは gzip 後 15 KB 未満（ビルドで検査します）。`wordpress/askai-button/assets/askai-button.min.js` はビルド成果物なので編集・コミットしません。PHP は 7.4 で動く書き方にし、入力はサニタイズ、出力はエスケープします。
- 開発コマンド: `npm install`、`npm test`、`npm run typecheck`、`npm run build`、`npm run test:php`、`npm run i18n`、`npm run zip:plugin`。
- 貢献したコードのライセンスは、変更した部分のライセンスに従います（コアは MIT、プラグインは GPL-2.0-or-later）。
