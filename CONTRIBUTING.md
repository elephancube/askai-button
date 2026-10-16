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
- Storing or analysing questions, pages, or anything about a visitor.
- Bundling service logos.
- Adding runtime dependencies.
- Anything that sends data to a third party. (The WordPress plugin's optional usage counts report a click to the site itself, as a kind of click and the id of a service, and store monthly totals only.)

## Development setup

```sh
npm install
npm test              # unit tests (Vitest)
npm run typecheck
npm run build         # builds the script, checks its size, copies it into the plugin
npm run test:php      # plugin tests; needs PHP on your PATH
npm run i18n          # regenerates the plugin translation files
npm run zip:plugin    # writes dist-plugin/elephancube-ask-ai-button-<version>.zip (run the build first)
npm run check:versions  # the version number is the same everywhere it is written
```

## Rules for pull requests

- All front-end behaviour (UI, query building, language detection) lives in `packages/core`. The WordPress plugin only passes configuration to it; do not re-implement logic in PHP or in the plugin's JavaScript.
- UI strings live only in the locale dictionaries in `packages/core/src/locales/`. Every language must have exactly the same keys as `en.ts`.
- Never put text into the page with `innerHTML`. Text goes in through `textContent`, and the question goes into URLs through `encodeURIComponent`.
- URL templates are configuration. Never assume a service's URL parameter works.
- No runtime dependencies. The built script must stay under 15 KB gzip; `npm run build` fails if it does not.
- `wordpress/elephancube-ask-ai-button/assets/askai-button.min.js` is a build artefact (it is git-ignored). Do not edit or commit it.
- PHP must be valid for PHP 7.4. Sanitize input and escape output.
- CSS lives inside the Shadow DOM and uses logical properties. Do not give buttons or chips a fixed width.

### Adding a language

There are two ways to provide a dictionary.

1. Built in: add `packages/core/src/locales/<code>.ts`, exporting a `Dict` (the type from `en.ts`) with every key of `en.ts`, then register it in the `locales` map in `packages/core/src/i18n.ts` next to `en` and `ja`. Add or update tests.
2. At runtime, without touching the library: call `registerLocale('<code>', { ... })` with some or all of the keys. Missing keys fall back to English. Codes are matched case-insensitively, and `pt-BR` falls back to `pt`.

Keep placeholders such as `{provider}`, `{question}` and `{site_url}` intact in translated strings. Built-in dictionaries must keep the same keys as English, including the per-service `note.*` keys.

### WordPress plugin translations

The plugin's own screens are translated with gettext files in `wordpress/elephancube-ask-ai-button/languages/`. Run `npm run i18n` after changing translatable strings in the PHP sources or a `.po` file. It rewrites the `.pot` and `.po` files and compiles the `.mo` files, and exits with an error if a `.po` file is missing a translation, has an unused one, or has placeholders that differ from the original.

### Tests

`npm test` runs the core tests in `packages/core/test/`. `npm run test:php` runs `wordpress/elephancube-ask-ai-button/tests/run.php`.

## Commits and pull requests

Keep them small and focused, with a short description of what changed and why. Mention how you tested it. No special commit format is required.

## Releasing

For maintainers. A release is one version number for the npm package (`@elephancube/askai-button`), the script and the WordPress plugin.

1. Make sure the "Unreleased" section of `CHANGELOG.md` describes everything that changed.
2. Set the version. The script writes the number to every place it appears (the package and the lock file, the plugin header and its version constant, `Stable tag` in `readme.txt`, the CDN addresses in the READMEs) and moves the "Unreleased" entries under a heading with the version and the date. With `--check` it only prints what it would do.

   ```sh
   npm run bump -- 1.2.3 --check
   npm run bump -- 1.2.3
   ```

3. Check the result, then commit it and get it onto `main` with green CI.

   ```sh
   npm run check:versions
   npm run typecheck
   npm test
   npm run build
   npm run test:php
   ```

4. Tag that commit `v` + the version and push the tag.

   ```sh
   git tag v1.2.3
   git push origin v1.2.3
   ```

5. The tag starts the "Release" workflow (`.github/workflows/release.yml`). It checks that the tag, the files and the changelog name the same version, runs the tests, builds, publishes the package to npm, and creates a GitHub release with `askai-button.min.js`, `askai-button.esm.js` and the plugin zip attached. The text of the release is the section of `CHANGELOG.md` for that version.
6. Check the result: the package page on npm (`npm view @elephancube/askai-button version`), the CDN address from the installation page, the files of the GitHub release, and the website, which is rebuilt from `main` and shows the new version number.

Things to know:

- A version number can be used only once on npm, even if the version is removed again. If something went wrong, fix it and release the next patch version.
- The website is deployed when `main` is pushed and names the new version in its CDN addresses, which do not work before the package is on npm. Push the tag right after `main`.
- The workflow publishes to npm with trusted publishing (OpenID Connect); no npm token is stored in the repository. The values to enter on npmjs.com are in the comment at the top of the workflow file. A package has to exist before a trusted publisher can be added, so the first version of a new package is published by hand, with `npm publish --access public` in `packages/core`; the workflow sees that the version is already on npm and only creates the GitHub release.
- To rehearse without publishing, start the workflow by hand (Actions > Release > Run workflow) and leave "dry run" ticked.
- A version with a suffix such as `1.3.0-beta.1` is published under the npm tag `next` and marked as a pre-release on GitHub.

## Licence of contributions

By submitting a contribution you agree that it is licensed under the licence of the part of the project it changes: MIT for the core library and everything outside `wordpress/`, GPL-2.0-or-later for the WordPress plugin in `wordpress/elephancube-ask-ai-button/`.

## 日本語の要約

- サポートはベストエフォートです。返信が遅れることがあります。
- 歓迎するもの: バグ報告、サービスのURL形式が変わったという報告（サービス名、気づいた日、ブラウザと端末、起きたことを書いてください）、翻訳、小さな修正。
- 対象外: AIのAPI呼び出し、質問・ページ・訪問者に関する情報の保存や分析、サービスのロゴの同梱、ランタイム依存の追加、データを第三者へ送る機能（WordPress プラグインの「利用回数の記録」は、クリックの種類とサービスの id をサイト自身にだけ送り、月ごとの合計だけを保存します）。
- フロントの挙動はすべて `packages/core` に置き、WordPressプラグインは設定を渡すだけにします。UI文言は `locales` の辞書だけに置き、キーは全言語で揃えます。`innerHTML` は使わず `textContent` を使います。URLテンプレートは設定値で、動作を前提にしません。配布JSは gzip 後 15 KB 未満（ビルドで検査します）。`wordpress/elephancube-ask-ai-button/assets/askai-button.min.js` はビルド成果物なので編集・コミットしません。PHP は 7.4 で動く書き方にし、入力はサニタイズ、出力はエスケープします。
- 開発コマンド: `npm install`、`npm test`、`npm run typecheck`、`npm run build`、`npm run test:php`、`npm run i18n`、`npm run zip:plugin`、`npm run check:versions`。
- リリース（メンテナー向け）: `CHANGELOG.md` の Unreleased を整え、`npm run bump -- <バージョン>` でバージョンを書き換えてコミットし、`v<バージョン>` のタグを push すると、GitHub Actions が npm への公開と GitHub リリースの作成を行います。手順の詳細は、上の「Releasing」を参照してください。
- 貢献したコードのライセンスは、変更した部分のライセンスに従います（コアは MIT、プラグインは GPL-2.0-or-later）。
