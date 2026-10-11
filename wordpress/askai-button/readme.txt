=== AskAI Button ===
Contributors: TODO-wordpress-org-username
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

* Display: switch the button on or off, limit it to some post types, choose the corner, the distance from the edges, the z-index and the colour of the button.
* Texts: the label of the button and the texts of the panel. A field left empty uses the built-in wording in the language of the page.
* Preset questions: up to six example questions a visitor can tap, and whether a tap fills in the question or opens the AI service at once.
* AI services: which services are offered, their order, the one selected by default, and for each service the URL template, the way the question is handed over (in the URL, or copied to the clipboard) and its colour.
* Query: the template of the text handed to the AI service, and the maximum length of a question.

A preview on the same screen shows the button and the panel with the values of the form before they are saved, together with the URL that would be opened for a sample question. The preview and the buttons that reorder questions and services need JavaScript; every setting can also be edited and saved without it.

= Per post =

The edit screen of posts, pages and other public post types has an "Ask AI" box with two settings: "Do not show the button on this page", and up to six "Questions for this page", which replace the questions of the site on that page.

= Languages =

The button is displayed in the language of the page. Japanese and English are built in; other languages fall back to English. The settings screen is available in English and Japanese.

= Multilingual sites: Polylang and WPML =

With the built-in wording, nothing needs to be set up: every page shows the button in its own language.

Texts that you type yourself (the five texts, the preset questions of the site and the query template) can be translated when Polylang, or WPML with its String Translation add-on, is active:

1. Type the texts in the main language of the site under Settings > Ask AI and save.
2. Open the string translation screen of the multilingual plugin (Polylang: Languages > Translations) and look for the group "AskAI Button". The strings are named `button_label`, `panel_title`, `panel_subtitle`, `placeholder`, `note`, `preset_1` to `preset_6` and `query_template`.
3. Enter the translations. A page shows the translation for its language, and the text you typed where there is none.

Good to know:

* Translations are cleaned in the same way as the texts of the settings screen: HTML is removed and the length is limited.
* The address of the site handed to the AI service is the front page in the language of the page, for example `https://example.com/en/`.
* "Questions for this page" and "Do not show the button on this page" belong to one post. Each translation of a post has its own, and they are not copied to the other translations: Polylang neither copies nor synchronises them, also when the synchronisation of custom fields is switched on.
* Polylang lists a string as long as its text is in use. When you change a question, the new text appears without a translation; the translation of the old text is kept by Polylang and comes back if you restore the old text. It can be removed there with "Clean strings translation database".
* WPML keeps a string until it is deleted on its own screen. After you remove a question, its entry (for example `preset_3`) stays there unused. WPML identifies the questions by their number, so check the translations after changing the order of the questions.
* Neither plugin is required, and nothing breaks when it is deactivated: the texts are then shown as typed.

= For developers =

The behaviour can be adjusted with the filters `askai_should_display`, `askai_presets`, `askai_providers`, `askai_query_template` and `askai_site_url`. Values returned by these filters are checked in the same way as the settings.

The button itself is the `askai-button` JavaScript library. The plugin only passes the settings to it. The source of the library and of this plugin is in one repository; the file `assets/askai-button.min.js` is built from it.

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

The plugin only passes an address. Whether and how a service opens that address is up to the service, and a site that blocks AI crawlers, requires a login or is not reachable from the internet cannot be read. For that case the panel has a section "If the AI cannot read this site" with a button that copies the question and the address, so the visitor can paste them, or the content of the page, into the AI service by hand.

= Can I change the URL the AI gets? =

Yes. By default it is the address of the front page of your site. The query template under Settings > Ask AI decides what is handed over: use `{url}` for the address of the page the visitor is on instead of `{site_url}`, or write any other text. The variables are `{question}`, `{site_url}`, `{url}`, `{title}` and `{site_name}`. Developers can replace the value of `{site_url}` with the `askai_site_url` filter.

= Does it work on a multilingual site? =

Yes. The built-in texts follow the language of each page. With Polylang or WPML, the texts you typed yourself can be translated on the string translation screen of that plugin. See "Multilingual sites" in the description.

= Can I hide the button on some pages? =

Yes. Tick "Do not show the button on this page" in the "Ask AI" box of the edit screen, or limit the button to some post types under Settings > Ask AI. Developers can also use the `askai_should_display` filter.

= What happens when I leave a text or a URL template empty? =

The built-in value is used. The built-in texts follow the language of the page, so an empty field is the right choice on a multilingual site.

= A URL template was rejected. Why? =

A template must start with `https://` and contain `{query}`, the place where the text for the AI service goes. Anything else is not saved and the built-in URL of that service is used.

= What is removed when I delete the plugin? =

Its settings and the two values it stores per post. Translations entered in Polylang or WPML are stored by those plugins and stay there.

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

== Trademarks ==

ChatGPT, Claude and Google are trademarks of their respective owners. This plugin is not affiliated with, sponsored by or endorsed by OpenAI, Anthropic or Google. No logos of these services are included.

== License ==

The plugin is licensed under the GNU General Public License, version 2 or any later version. The full text is in the file `LICENSE`.

The bundled script `assets/askai-button.min.js` is the `askai-button` library, Copyright (c) 2026 Elephancube Inc., released under the [MIT License](https://opensource.org/license/mit), which is compatible with the GPL. The file carries that notice.

== Changelog ==

= 0.1.0 =
* First version.
