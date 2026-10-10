=== AskAI Button ===
Contributors: TODO-wordpress-org-username
Tags: ai, chatgpt, claude, button
Requires at least: 6.4
Tested up to: TODO
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

This is version 0.1.0. It has no settings screen yet: after activation the button appears on the public pages of the site with its default settings. It is not shown in the admin area, in previews, on 404 pages, or on posts that are password protected or not published.

Developers can adjust the behaviour with the filters `askai_should_display`, `askai_presets`, `askai_providers` and `askai_query_template`.

The button is displayed in the language of the page. Japanese and English are built in.

== External services ==

The plugin itself contacts no external service. When a visitor presses the send button in the panel, their browser opens one of the following sites in a new tab. The question they typed and the address of your site are part of the address that is opened, so they are transmitted to the service the visitor chose. Nothing is sent before the visitor presses that button.

* ChatGPT, provided by OpenAI: [terms of use](https://openai.com/policies/terms-of-use/), [privacy policy](https://openai.com/policies/privacy-policy/)
* Claude, provided by Anthropic: [terms of service](https://www.anthropic.com/legal/consumer-terms), [privacy policy](https://www.anthropic.com/legal/privacy)
* Google Search in AI Mode, provided by Google: [terms of service](https://policies.google.com/terms), [privacy policy](https://policies.google.com/privacy)

What each service does with the question is governed by its own terms.

The URL parameters used to hand the question to these services are not documented by their providers. They may change or stop working at any time, and a service may behave differently depending on the visitor's region, account or device.

== Trademarks ==

ChatGPT, Claude and Google are trademarks of their respective owners. This plugin is not affiliated with, sponsored by or endorsed by OpenAI, Anthropic or Google. No logos of these services are included.

== Installation ==

1. Upload the `askai-button` folder to `/wp-content/plugins/`.
2. Activate the plugin on the Plugins screen.

== Frequently Asked Questions ==

= Does the AI service read my page? =

The plugin only passes an address. Whether and how a service opens that address is up to the service.

= Can I hide the button on some pages? =

Yes, with the `askai_should_display` filter. A settings screen is planned.

== License ==

The plugin is licensed under GPL-2.0-or-later. The bundled script `assets/askai-button.min.js` is built from the `askai-button` library, which is released under the MIT license; its source is in the same repository as this plugin.

== Changelog ==

= 0.1.0 =
* First version.
