## What and why

<!-- A short description of the change and the reason for it. -->

## Checklist

- [ ] Front-end behaviour is in `packages/core`; the WordPress plugin only passes configuration.
- [ ] UI strings are only in the locale dictionaries, with the same keys in every language.
- [ ] No `innerHTML`; text goes in through `textContent`.
- [ ] URL templates stay configurable and are not assumed to work.
- [ ] No new runtime dependency, and the build keeps the script under 15 KB gzip.
- [ ] `wordpress/elephancube-ask-ai-button/assets/askai-button.min.js` is not edited or committed.
- [ ] PHP is valid for 7.4; input is sanitized and output is escaped.
- [ ] `npm test`, `npm run typecheck` and (for plugin changes) `npm run test:php` pass.
- [ ] I agree that my contribution is licensed under MIT (core) or GPL-2.0-or-later (plugin), as described in CONTRIBUTING.md.
