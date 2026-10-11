# Security Policy

## Supported versions

Only the latest release receives security fixes.

## Reporting a vulnerability

Please use GitHub's private vulnerability reporting for this repository: open the Security tab and choose "Report a vulnerability".

Please do not open a public issue, pull request or discussion for a vulnerability.

Useful things to include:

- Which part is affected (the script, the npm package, or the WordPress plugin) and its version.
- For the plugin, the WordPress and PHP versions.
- Steps to reproduce, ideally a minimal page or configuration.
- What you expected, what happened, and the impact you see.

This is a small project and support is best effort. There is no guaranteed response or fix time.

## Scope

In scope: the `askai-button` script (including the npm package) and the WordPress plugin in this repository, for example script injection through options or settings, unsafe handling of the question or the URL templates, or missing capability, nonce, sanitising or escaping checks in the plugin.

Out of scope:

- The behaviour of the third-party AI services (ChatGPT, Claude, Google AI Mode) and what they do with the text they receive.
- The fact that the question is visible in the URL of the service the visitor chose. This is by design: the question is handed over in the URL parameter of that service, in the visitor's own browser, when the visitor presses the send button. Services may log URLs they receive; that is governed by their own terms.
- Problems that need a site owner or administrator to deliberately configure something harmful, such as pointing a URL template at an untrusted address.
