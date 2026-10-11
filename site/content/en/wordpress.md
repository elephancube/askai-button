---
title: WordPress plugin
description: About "AskAI Button", the WordPress plugin of askai-button - its requirements, the settings screen, the per-post settings, where the button is never shown, the filters for developers, how it behaves with Polylang and WPML, and what is removed on uninstall.
nav: WordPress
---

The plugin "AskAI Button" adds the "Ask AI" button to a WordPress site and lets you set it up in the admin area. The button itself is the JavaScript library (askai-button); the plugin only passes the settings to it.

For the installation steps, see "[Install]({{page:install}}#wordpress)".

## Requirements {#requirements}

- WordPress 6.4 or later
- PHP 7.4 or later

After activation the button appears on the public pages of the site with its default settings. The plugin calls no AI API and needs no API key. Your server makes no requests to anyone. With the default settings it stores nothing about the use of the button; the optional "[Usage counts](#usage)" keep monthly totals on your own site.

## The settings screen {#settings}

Everything is on one screen, "Settings > Ask AI". It can be changed by administrators (users with the `manage_options` capability).

### Display

| Setting | Meaning |
| --- | --- |
| Show the button on the site | Switches the whole button on or off. |
| Post types | The post types on which the button is shown. With none ticked, it is shown on all of them. This limits the pages of single posts and pages only: lists such as the home page and archives always show the button. |
| Position | Bottom right or bottom left. |
| Distance from the edges | 0 to 500px. The default is 24px. |
| Stacking order (z-index) | Raise this number if another element of your theme covers the button. The default is 9999. |
| Launcher colour | A colour such as `#1a73e8`. The text is white, so choose a dark colour. Empty means the built-in colour. |
| On multilingual sites, pass the front page of the language being viewed | Shown only while Polylang or WPML is active. Off by default: the top URL of the site is passed on every page. See "[Multilingual sites](#multilingual)". |

### Texts

Five texts can be changed: the button label, the panel title, the panel subtitle, the placeholder of the question field, and the note. A field left empty uses the built-in wording. The built-in wording follows the language of the page, so on a multilingual site leaving the fields empty is the easy choice.

The texts are plain text of up to 200 characters. HTML tags are removed.

### Preset questions

Up to six "example questions" that a visitor can tap. They can be reordered.

"When a question is tapped" is either "Put it into the question field" (the default) or "Open the AI service at once".

### AI services

For each of ChatGPT, Claude and Google AI Mode you can set the following. The order of the services can be changed as well.

| Setting | Meaning |
| --- | --- |
| Offer this service | Whether it is one of the choices in the panel. With every service switched off, the button is not shown. |
| Selected by default | The service that is selected when the panel opens. |
| URL template | The address that opens the service. It must start with `https://` and contain `{query}`. Empty means the built-in URL. |
| How the question is handed over | "In the URL" (the default) or "Copied to the clipboard, then the service is opened". |
| Colour | Colour of the selected choice and of the send button. Empty means the built-in colour. |

The URL formats are not documented by the services and can change without notice. When one stops working, you can correct its URL template here, switch the way the question is handed over, or switch the service off.

### Query

| Setting | Meaning |
| --- | --- |
| Query template | The text handed to the AI service. Empty means the built-in text in the language of the page. The variables are `{question}`, `{site_url}`, `{url}`, `{title}` and `{site_name}`. |
| Maximum question length | 1 to 2000. The default is 500. |

`{site_url}` is the address of the front page of the site (`home_url('/')`). On a site run with Polylang or WPML it is the front page of the default language, on pages of every language, unless the setting "On multilingual sites, pass the front page of the language being viewed" is ticked. To hand over the address of the page the visitor is on, use `{url}` in the template.

### Usage counts

| Setting | Meaning |
| --- | --- |
| Count how often the button and each AI service are used | Off by default. When ticked, the plugin keeps monthly totals on your site. See "[Usage counts](#usage)". |

### Preview

The settings screen has a preview that shows the button and the panel with the values of the form before they are saved. With a sample question it also shows the URL that would be opened. The preview and the buttons that reorder items need JavaScript; every setting can be edited and saved without it.

## Per-post settings {#per-post}

The edit screen of posts, pages and other public post types gets a box named "Ask AI".

- **Do not show the button on this page**: when ticked, the button is left out on that page.
- **Questions for this page**: up to six. When any are set, they replace the preset questions of the site on that page.

## Usage counts {#usage}

Counting is off by default. When you tick "Count how often the button and each AI service are used" under "Settings > Ask AI" and save, the plugin counts four things per calendar month, in the time zone of the site:

- how often the "Ask AI" button opened the panel
- how often a question was sent to ChatGPT, to Claude and to Google AI Mode

A service added with the `askai_providers` filter is counted under its id and gets a column of its own.

**What is stored.** One whole number per month and counter, in the database of your own site (the options table; the rows are not loaded on ordinary page views). Nothing else is stored: not the question, not the address of the page, no IP address, no browser information, no cookie, no account, and no time finer than the month.

**How a click is reported.** While counting is on, pages that show the button also load a small script, `counter.js`. For each of these clicks it sends one request to your own site (`POST /wp-json/askai-button/v1/count`) that names the kind of click (`open` or `send`) and, for a question, the id of the service. Nothing is sent to anyone else, and the JavaScript library of the button still makes no request by itself. The request does not delay the opening of the AI service, and a request that fails is dropped.

**The numbers are approximate.** Bots and repeated clicks are counted. Clicks of logged-in users who can edit posts (authors, editors, administrators) are not counted: the script is not loaded for them. A page cache does not get in the way, because the page contains nothing that belongs to a single visitor. The endpoint accepts only the two kinds of click and the services the button offers at the moment, and refuses requests that a browser marks as coming from another site.

**Where to see them.** The section "Usage" at the bottom of "Settings > Ask AI" shows one row per month, newest first, and a row of totals. "Download CSV" saves the table as a file. "Reset counts" deletes all counts after you tick the box next to it. Saving the settings does not change the counts. When you switch counting off, the counts stay visible and nothing new is added.

## Where the button is never shown {#never}

In these places the button is not shown, whatever the settings and the filters say:

- the admin area and the login screen
- previews, including the preview of the Customizer
- 404 pages
- attachment pages
- posts that are not published, and posts protected by a password
- responses that are not pages, such as feeds, embeds and the REST API

A button is of no use on a page that an AI service cannot read.

## Filters {#filters}

Themes and plugins can change the behaviour with the following filters. Values returned by a filter are checked in the same way as the values of the settings screen.

### askai_should_display

```php
apply_filters( 'askai_should_display', bool $show, WP_Post|null $post )
```

Whether the button is shown on the current page. `$show` is the value that follows from the "Post types" setting and the per-post switch; `$post` is the post on single views and `null` elsewhere. The button is shown only when the filter returns `true`. The filter is not called in the places listed under "[Where the button is never shown](#never)".

```php
add_filter( 'askai_should_display', function ( $show, $post ) {
	// Not on posts in the "members" category.
	if ( $post && in_category( 'members', $post ) ) {
		return false;
	}
	return $show;
}, 10, 2 );
```

### askai_presets

```php
apply_filters( 'askai_presets', string[] $presets, WP_Post|null $post )
```

The example questions shown in the panel. `$presets` holds the questions of the post if it has any, otherwise those of the site. Of the returned value, up to six non-empty strings are used.

```php
add_filter( 'askai_presets', function ( $presets, $post ) {
	if ( $post && 'product' === $post->post_type ) {
		return array( 'How much is shipping?', 'Can I return this item?' );
	}
	return $presets;
}, 10, 2 );
```

### askai_providers

```php
apply_filters( 'askai_providers', array $providers )
```

The AI services handed to the script. Each entry is either the id of a built-in service (`chatgpt`, `claude`, `googleai`) or an array with the key `id` and, optionally, `name`, `urlTemplate`, `method` (`url` or `copy_and_open`) and `color` (a hex colour). A service that is not built in needs a `name` and a `urlTemplate` that starts with `https://` and contains `{query}`. Entries that do not meet these rules are dropped.

```php
add_filter( 'askai_providers', function ( $providers ) {
	$providers[] = array(
		'id'          => 'example',
		'name'        => 'Example AI',
		'urlTemplate' => 'https://ai.example.com/search?q={query}',
		'color'       => '#444444',
	);
	return $providers;
} );
```

`ai.example.com` is a made-up address for this explanation.

### askai_query_template

```php
apply_filters( 'askai_query_template', string $query_template )
```

The template of the text handed to the AI service. An empty string means the built-in text in the language of the page.

```php
add_filter( 'askai_query_template', function ( $query_template ) {
	return "{question}\n\nPlease refer to this page to answer.\n{url}";
} );
```

### askai_site_url

```php
apply_filters( 'askai_site_url', string $site_url, bool $lang_home )
```

The address of the site handed to the AI service as `{site_url}`. `$site_url` is the address the plugin chose: the front page of the site, which on a multilingual site is the front page of the default language or, with the setting ticked, the front page of the language of the current page. `$lang_home` is the value of that setting, "On multilingual sites, pass the front page of the language being viewed". The filter runs last, whatever the setting. The returned value is used only if it is a string holding an absolute address that starts with `http://` or `https://`; anything else is ignored and `$site_url` is used.

```php
add_filter( 'askai_site_url', function ( $site_url, $lang_home ) {
	// A site that lives below the top of its domain.
	return 'https://example.com/shop/';
}, 10, 2 );
```

## Multilingual sites: Polylang and WPML {#multilingual}

With the built-in wording, nothing needs to be set up: every page shows the button in its own language. Japanese and English are built in; pages in other languages show the button in English. The settings screen is available in English and Japanese.

Texts that you type yourself (the five texts, the preset questions of the site and the query template) can be translated when Polylang, or WPML with its String Translation add-on, is active.

1. Type the texts in the main language of the site under "Settings > Ask AI" and save.
2. Open the string translation screen of the multilingual plugin (in Polylang: "Languages > Translations") and look for the group "AskAI Button". The strings are named `button_label`, `panel_title`, `panel_subtitle`, `placeholder`, `note`, `preset_1` to `preset_6` and `query_template`.
3. Enter the translations. A page shows the translation for its language, and the text you typed where there is none.

Good to know:

- Translations are processed in the same way as the texts of the settings screen: HTML is removed and the length is limited.
- The address of the site handed to the AI service is the top URL of the site on every page, whatever the language of the page: the front page of the default language, for example `https://example.com/`. When the default language has a prefix of its own, as in `https://example.com/ja/`, that address is passed, not the bare domain, which would only redirect.
- To pass the front page of the language being viewed instead, tick "On multilingual sites, pass the front page of the language being viewed" under "Settings > Ask AI > Display". An English page then passes, for example, `https://example.com/en/`. The setting is shown only while Polylang or WPML is active and has no effect without them. Only the address changes: the wording and the language of the button always follow the page. The `askai_site_url` filter can replace the address in both cases.
- "Questions for this page" and "Do not show the button on this page" belong to one post. Each translation of a post has its own, and they are not copied to the other translations. Polylang neither copies nor synchronises them, also when the synchronisation of custom fields is switched on.
- Polylang lists a string only as long as its text is in use. When you change a question, the new text appears without a translation. The translation of the old text is kept by Polylang and is used again if you restore the old text. It can be removed in Polylang with "Clean strings translation database".
- WPML keeps a string until it is deleted on WPML's own screen. After you remove a question, its entry (for example `preset_3`) stays there unused. WPML identifies the questions by their number, so check the translations after changing the order of the questions.
- Polylang has been checked on a live WordPress site. The WPML integration is written against the hooks that WPML documents (`wpml_register_single_string` and `wpml_translate_single_string` for the texts, `wpml_default_language` and `wpml_permalink` for the address of the site) and has not yet been run on a WPML installation.
- Neither plugin is required. Nothing breaks when one is deactivated: the texts are then shown as typed.

## Uninstall {#uninstall}

Deleting the plugin removes everything it stored:

- the settings (the option `askai_settings`)
- the two values per post (the post meta `_askai_disabled` and `_askai_presets`)
- the usage counts (the options whose names start with `askai_count_`)

On a multisite network they are removed from every site. Translations entered in Polylang or WPML are stored by those plugins and stay there. Deactivating the plugin removes nothing.

## External services {#external}

The plugin itself contacts no external service. When a visitor presses the send button, the visitor's browser opens the chosen AI service in a new tab. This is the only moment at which data leaves your site. The optional "[Usage counts](#usage)" are reported to your own site only. What is handed over is described under "[How it works and caveats]({{page:how-it-works}}#privacy)".
