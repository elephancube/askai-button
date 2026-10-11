---
title: Install
description: There are three ways to add askai-button to a site - one script tag, the npm package, or the WordPress plugin. This page gives the steps for each and explains how to check that it works.
nav: Install
---

All three give you the same button. Choose the one that fits how your site is built.

| Method | Good for |
| --- | --- |
| [Script tag](#script) | Sites where you can edit the HTML directly. No build step is needed. |
| [npm](#npm) | Sites and web apps that bundle their JavaScript. |
| [WordPress plugin](#wordpress) | WordPress sites. Everything is set in the admin area. |

## With a script tag {#script}

### 1. Put the file on your own site

[Download askai-button.min.js]({{file:askai-button.min.js}}) and put it on the server of your site ({{min_kb}} KB, about {{gzip_kb}} KB after gzip compression). It can go anywhere. The examples below assume `/js/askai-button.min.js`.

This one file contains the whole behaviour of the button and its CSS. Nothing else is loaded.

### 2. Add one line

Add this line to every page that should show the button:

```html
<script src="/js/askai-button.min.js" data-askai></script>
```

With `data-askai`, the button appears as soon as the file is loaded. The tag can go inside `<head>` or just before `</body>`. It also works with `defer`.

### A complete minimal example

Save the following HTML and put `askai-button.min.js` in the same folder.

```html
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sample page</title>
  </head>
  <body>
    <h1>Sample page</h1>
    <p>Press the button at the bottom right to open the question panel.</p>

    <script src="askai-button.min.js" data-askai
      data-site-url="https://example.com/"
      data-presets="What can I do on this site?|How much does it cost?"></script>
  </body>
</html>
```

`data-site-url` is the address of the site that is handed to the AI. On pages served by a web server you can leave it out, and the top of the site (`https://your-domain/`) is used automatically. It is set in this example so that you can try the file by opening it directly.

### Attributes you are likely to need

Settings are given as `data-*` attributes.

| Attribute | Meaning | Example |
| --- | --- | --- |
| `data-presets` | Example questions, separated by `\|`. Up to six. | `data-presets="Price?\|Delivery time?"` |
| `data-site-url` | The address of the site handed to the AI. Set it when the site is not at the top of its domain. | `data-site-url="https://example.com/shop/"` |
| `data-providers` | The AI services to offer and their order, separated by `,`. | `data-providers="chatgpt,claude"` |
| `data-position` | The corner of the button. | `data-position="bottom-left"` |
| `data-accent-color` | The colour of the button. | `data-accent-color="#27455c"` |
| `data-lang` | Fixes the language. Usually not needed. | `data-lang="en"` |

All attributes are listed in the "[Reference]({{page:reference}}#data-attributes)".

### Setting it up from JavaScript

Some settings, such as changing the wording or adding an AI service, cannot be given as attributes. For those, load the file without `data-askai` and call `AskAIButton.init()`.

```html
<script src="/js/askai-button.min.js"></script>
<script>
  AskAIButton.init({
    presets: ['What can I do on this site?', 'How much does it cost?'],
    providers: ['chatgpt', 'claude'],
    labels: { buttonLabel: 'Ask an AI' },
  });
</script>
```

## With npm {#npm}

```sh
npm install askai-button
```

```js
import { init } from 'askai-button';

init({
  presets: ['What can I do on this site?', 'How much does it cost?'],
});
```

- The package is an ES module and includes TypeScript type definitions.
- Call `init()` once, in the browser. Importing the package on a server causes no error and does nothing.
- Calling `init()` again rebuilds the button with the new settings. Call `destroy()` to remove it.
- `dist/askai-button.min.js` in the package is the same file as the one for the script tag.

From a component, for example in React, call it once after the component is shown.

```js
import { useEffect } from 'react';
import { init, destroy } from 'askai-button';

export function AskAI() {
  useEffect(() => {
    init({ presets: ['How much does it cost?'] });
    return () => destroy();
  }, []);
  return null;
}
```

The options are listed in the "[Reference]({{page:reference}}#options)".

## With the WordPress plugin {#wordpress}

The plugin needs WordPress 6.4 or later and PHP 7.4 or later.

1. Download the zip file of the plugin (`askai-button-{{version}}.zip`) from the [releases page on GitHub]({{repo}}/releases).
2. In the WordPress admin area, open "Plugins > Add New Plugin > Upload Plugin", choose the zip file and install it.
3. Activate "AskAI Button" in the list of plugins.
4. Open the site: the button is at the bottom right of the public pages. The settings are under "Settings > Ask AI".

Activating the plugin is enough to start with the default settings. The settings screen is described on the "[WordPress]({{page:wordpress}})" page.

The plugin is distributed on GitHub. It is not listed in the WordPress.org plugin directory.

### Building the zip from source

Clone the repository and run the following commands. They write `dist-plugin/askai-button-{{version}}.zip`. Node.js is required.

```sh
git clone {{repo}}.git
cd askai-button
npm install
npm run build
npm run zip:plugin
```

## Check that it works {#check}

1. The "Ask AI" button is at the bottom right of the page (on a Japanese page it says "AIに質問").
2. Pressing it opens the panel, with the cursor in the question field.
3. After typing a question, the send button at the bottom can be pressed.
4. Pressing the send button opens the chosen AI service in a new tab, with the question, the fixed sentence and the address of the site handed over.
5. The address handed over is the top of your site.

If the address in step 5 is wrong, set it with `data-site-url` (`siteUrl` in `init()`). This is needed when the site is not at the top of its domain but below it, as in `https://example.com/shop/`.

### When the button does not appear

- Check the path of the file: the developer tools of the browser show whether `askai-button.min.js` was loaded.
- Check that the script tag has `data-askai`, or that `AskAIButton.init()` is called.
- A page with `<meta name="askai" content="off">` does not show the button.
- Check that `providers` does not list only ids of AI services that do not exist. Without a usable AI service, the button is not shown.
- On a site with a Content Security Policy, allow the file in `script-src`.

### Opening a file directly (file://) {#file}

The button also appears when you open an HTML file by double-clicking it (the address starts with `file://`). The location of a file on your computer means nothing to an AI, though, so no address is handed over: the text ends with the fixed sentence, and the place of the address stays empty.

To see the address being handed over, do one of the following:

- Set the address of the site, as in `data-site-url="https://example.com/"`.
- Serve the file from a web server on your computer and open it at an address such as `http://localhost:8000/`.

An AI service cannot read `localhost` or an internal staging site. During development, check only that the text and the address are handed over correctly, and check whether the AI answers from the content of your site after it is public.
