=== AskAI Button ===
Contributors: elephancube
Tags: ai, chatgpt, claude, button, multilingual
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A floating "Ask AI" button that hands the visitor's question and your site URL to ChatGPT, Claude or Google AI Mode in a new tab.

== Description ==

This plugin adds a small button to the corner of your site. A visitor types a question, picks an AI service, and that service opens in a new browser tab with the question and the address of your site already filled in.

What it does not do:

* It calls no AI API and needs no API key.
* It makes no requests from your server or from the visitor's browser.
* It does not store, send or measure the question. The only thing kept is the visitor's last choice of AI service, in the browser's local storage.
* It sets no cookies.

After activation the button appears on the public pages of the site with its default settings. It is not shown in the admin area, in previews, on 404 pages, on attachment pages, or on posts that are password protected or not published.

= Settings =

Everything is set on one screen, Settings > Ask AI:

* Display: switch the button on or off, limit it to some post types, choose the corner, the distance from the edges, the z-index and the colour.
* Texts: the label of the button and the texts of the panel. A field left empty uses the built-in wording in the language of the page.
* Preset questions: up to six example questions a visitor can tap.
* AI services: which services are offered, their order and default, and for each one the URL template, the way the question is handed over (in the URL, or copied to the clipboard) and its colour.
* Query: the template of the text handed to the AI service, and the maximum length of a question.

A preview on the same screen shows the button and the panel before the settings are saved, with the URL that would be opened for a sample question. Every setting can also be edited and saved without JavaScript.

The edit screen of posts, pages and other public post types has an "Ask AI" box: "Do not show the button on this page", and up to six "Questions for this page", which replace the questions of the site on that page.

= Languages and multilingual sites =

The button is displayed in the language of the page. Japanese and English are built in; other languages fall back to English. The settings screen is available in English and Japanese.

With Polylang, or WPML with its String Translation add-on, the texts you typed yourself (the five texts, the preset questions and the query template) appear on the string translation screen of that plugin, in the group "AskAI Button". Neither plugin is required. Polylang has been checked on a live site; the WPML integration follows the hooks that WPML documents and has not yet been run on a WPML installation.

The details, and the filters for developers (`askai_should_display`, `askai_presets`, `askai_providers`, `askai_query_template`, `askai_site_url`), are in the [documentation](https://elephancube.github.io/askai-button/en/wordpress/).

= Source code =

The file `assets/askai-button.min.js` is minified. It is built from the TypeScript sources of the `askai-button` library, which are public, together with the sources of this plugin, in one repository: [github.com/elephancube/askai-button](https://github.com/elephancube/askai-button). The library is in the folder `packages/core`, this plugin in `wordpress/askai-button`.

To build the script you need Node.js with npm. In a copy of the repository, run `npm ci` and then `npm run build`: this compiles the library with Vite into `packages/core/dist/askai-button.min.js` and copies that file to `wordpress/askai-button/assets/`. `npm run zip:plugin` then packs the plugin as it is distributed. Nothing else in the plugin is compiled or minified.

== Installation ==

1. Upload the `askai-button` folder to `/wp-content/plugins/`, or upload the zip file under Plugins > Add New Plugin > Upload Plugin.
2. Activate the plugin on the Plugins screen.
3. Optionally, adjust the button under Settings > Ask AI.

== Frequently Asked Questions ==

= Does the plugin call an AI API? Do I need an API key or an account? =

No. The plugin opens the public web page of the AI service the visitor chose, in the visitor's browser. There is no API call, no key and no cost on your side. Whether the visitor needs an account is up to the AI service.

= What is sent, and to whom? =

Nothing is sent until the visitor presses the send button. Then the visitor's browser opens the chosen AI service, and the address that is opened contains the text for the AI: the question, one sentence asking the AI to refer to your site, and the address of your site. Your server receives nothing and sends nothing. See "External services" below.

= What if the AI cannot read my site? =

The plugin only passes an address. Whether and how a service opens it is up to the service, and a site that blocks AI crawlers, requires a login or is not reachable from the internet cannot be read. For that case the panel has a section "If the AI cannot read this site" with a button that copies the question and the address, so the visitor can paste them, or the content of the page, into the AI service by hand.

= Can I change the URL the AI gets? =

Yes. By default it is the address of the front page of your site. The query template under Settings > Ask AI decides what is handed over: use `{url}` for the address of the page the visitor is on instead of `{site_url}`, or write any other text. The variables are `{question}`, `{site_url}`, `{url}`, `{title}` and `{site_name}`.

= Does it work on a multilingual site? =

Yes. The built-in texts follow the language of each page, and texts you typed yourself can be translated with Polylang or WPML. The address handed to the AI service is the front page of the default language on every page; with Polylang or WPML active, a setting under Display passes the front page of the language being viewed instead.

= Can I hide the button on some pages? =

Yes. Tick "Do not show the button on this page" in the "Ask AI" box of the edit screen, or limit the button to some post types under Settings > Ask AI.

= A URL template was rejected. Why? =

A template must start with `https://` and contain `{query}`, the place where the text for the AI service goes. Anything else is not saved and the built-in URL of that service is used. An empty field also means the built-in URL.

= What is removed when I delete the plugin? =

Its settings and the two values it stores per post. Translations entered in Polylang or WPML are stored by those plugins and stay there.

== Screenshots ==

1. The button in the corner of a page.
2. The panel: the visitor types a question, or taps a preset question, and chooses an AI service.
3. The settings screen, Settings > Ask AI, with the live preview.
4. The AI services on the settings screen: order, URL templates and colours.
5. The "Ask AI" box on the edit screen of a post or page.

== External services ==

The plugin itself contacts no external service: neither your server nor the script in the visitor's browser makes a request to anyone.

When a visitor presses the send button in the panel, their browser opens the AI service they chose in a new tab. This is the only moment at which data leaves the page, and it happens only on that action of the visitor.

What is transmitted: the address that is opened contains the text for the AI. With the default settings this text consists of the question the visitor typed, one fixed sentence, and the address of your site. If you change the query template, it contains what your template says, which can include the address and the title of the current page and the name of the site. As with any link, the service also receives what a browser sends with every page request, such as the visitor's IP address and the cookies of that service. For a service set to "Copied to the clipboard", the text is copied to the visitor's clipboard and the service is opened without it.

The services, and the addresses opened by default:

* ChatGPT (`https://chatgpt.com/`), provided by OpenAI: [terms of use](https://openai.com/policies/terms-of-use/), [privacy policy](https://openai.com/policies/privacy-policy/)
* Claude (`https://claude.ai/`), provided by Anthropic: [terms of service](https://www.anthropic.com/legal/consumer-terms), [privacy policy](https://www.anthropic.com/legal/privacy)
* Google Search in AI Mode (`https://www.google.com/`), provided by Google: [terms of service](https://policies.google.com/terms), [privacy policy](https://policies.google.com/privacy)

Each service can be switched off under Settings > Ask AI. What a service does with the question is governed by its own terms.

The URL parameters used to hand the question to these services are not documented by their providers. They may change or stop working at any time, and a service may behave differently depending on the visitor's region, account or device.

== Trademarks and license ==

ChatGPT, Claude and Google are trademarks of their respective owners. This plugin is not affiliated with, sponsored by or endorsed by OpenAI, Anthropic or Google. No logos of these services are included.

The plugin is licensed under the GNU General Public License, version 2 or any later version; the full text is in the file `LICENSE`. The bundled script `assets/askai-button.min.js` is the `askai-button` library, Copyright (c) 2026 Elephancube Inc., released under the [MIT License](https://opensource.org/license/mit), which is compatible with the GPL. The file carries that notice.

== Changelog ==

= 0.1.0 =
* First version.
