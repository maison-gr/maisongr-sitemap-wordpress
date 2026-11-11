# Contributing

Thank you for helping. Bug reports with a way to reproduce them are the most useful thing you can send. Pull requests are welcome too.

## Setup

Development needs Node 22.12 or later. You don't need PHP, MySQL or Docker: the tests run WordPress in WebAssembly with [Playground](https://wordpress.github.io/wordpress-playground/).

```sh
npm ci
npm test                             # PHP 8.3, the latest WordPress
WP_PHP=7.4 WP_VERSION=6.5 npm test   # the oldest versions the plugin supports
```

## What a change needs

- **A test that fails without it.** Bug fixes come with the test that reproduces the bug, in `test/plugin.test.mjs`. The test site is set up by `test/blueprint.json`.
- **The same page as the package.** The page, its stylesheet and the XML must match [maisongr-sitemap](https://github.com/maison-gr/maisongr-sitemap)'s byte for byte; the parity tests check it. A change to the page's design belongs in the package first, then here with `npm run assets`.
- **No edits to `maisongr-sitemap/assets/`.** `page-rules.css` and `page.js` are generated from the package. CI regenerates them and fails on any difference.
- **PHP 7.4 and WordPress 6.5.** No PHP 8 syntax (`match`, `str_starts_with`, named arguments), and no WordPress function newer than 6.5.
- **Every value escaped** where it's written: `Page::esc` and `Xml::escape` in the pure classes, `esc_html` and friends in WordPress output.
- **Nothing sent anywhere.** The plugin makes no remote request; keep it that way.

## Commits and pull requests

Small commits, each doing one thing, with a [conventional](https://www.conventionalcommits.org/) message: `feat(entries): list WooCommerce product categories`, `fix(theme): read a classic theme's accent`. Keep a pull request to one change, and describe what you checked.

## Security

Please don't open a public issue for a security problem. See [SECURITY.md](SECURITY.md).
