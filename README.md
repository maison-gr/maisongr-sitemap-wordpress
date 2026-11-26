# Maison GR Sitemap for WordPress

**One sitemap, two readers.** Search engines get the XML they need at `/sitemap.xml`. A person who opens the same address gets a page set in your theme's own colours and type, with every page listed, grouped by section and searchable.

[![CI](https://github.com/maison-gr/maisongr-sitemap-wordpress/actions/workflows/ci.yml/badge.svg)](https://github.com/maison-gr/maisongr-sitemap-wordpress/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-1b1a17)](LICENSE)
![WordPress 6.5+](https://img.shields.io/badge/WordPress-%E2%89%A56.5-1b1a17)
![PHP 7.4+](https://img.shields.io/badge/PHP-%E2%89%A57.4-1b1a17)
![No settings to learn](https://img.shields.io/badge/settings-none-1b1a17)

![Maison GR open source, 02: Maison GR Sitemap, for WordPress. At your.site/sitemap.xml, Googlebot and Bingbot get the XML sitemap and a person in a browser gets a page in the theme's design; every page listed, WordPress's own sitemap redirected.](.github/assets/hero.jpg)

It's the WordPress edition of [maisongr-sitemap](https://github.com/maison-gr/maisongr-sitemap), the sitemap tool for Next.js, Astro, Nuxt and other frameworks. Both render the same page from the same design, and a test checks they match byte for byte.

## What it lists

- The home page, and every published post, page and public custom post type, each dated from its last edit.
- The archive of each category, tag and public taxonomy term in use.
- Each post's featured image, for image search.

It leaves out:

- pages an SEO plugin marks `noindex`: Yoast SEO, Rank Math, SEOPress and The SEO Framework. A sitemap that lists a `noindex` page asks search engines two opposite things;
- password-protected, private and draft posts;
- attachment pages.

## How the two readers are told apart

A browser opening a page sends `Sec-Fetch-Dest: document` and asks for HTML. Crawlers send neither. Those two headers decide which answer is served; no user agent is read. Both answers list exactly the same addresses. The page is `noindex`, cached privately, and `Vary: Sec-Fetch-Dest` keeps any cache from handing one to the other.

The XML also answers `If-None-Match` with `304 Not Modified`, so a crawler skips an unchanged sitemap. Past 50,000 addresses it becomes an index of `?part=N` files.

## What it changes on your site

- WordPress's own sitemap (`/wp-sitemap.xml`) is turned off, and its addresses redirect to `/sitemap.xml` with a 301.
- `robots.txt` names the sitemap, unless the site asks search engines not to index it.
- If Yoast SEO, Rank Math or All in One SEO also makes a sitemap, an admin notice says how to turn theirs off, so search engines read one list.

Deactivating the plugin brings WordPress's own sitemap back.

## Install

Install it from the [WordPress.org plugin directory](https://wordpress.org/plugins/maisongr-sitemap/): in WordPress, open **Plugins → Add New Plugin**, search for "Maison GR Sitemap", install and activate it, then open `/sitemap.xml`.

To try a build of this repository instead, make the zip (`npm ci && npm run zip`, see [Working on the plugin](#working-on-the-plugin)) and upload it under **Plugins → Add New Plugin → Upload Plugin**.

Requires WordPress 6.5 or later and PHP 7.4 or later.

## Your theme's look

The page reads your theme's colours and fonts from `theme.json` and the Site Editor's global styles: background, text, links, and the fonts your text and headings are set in, served from your own site. A classic theme gets its custom background colour, if it has one. The accent is used only when it reads at 3:1 on the background; otherwise the text colour stands in for it.

## Languages

With Polylang or WPML, each translation is linked to the others with `hreflang`, including `x-default`, and the page shows one row per page with its language versions beside it. Unpublished translations are left out.

Polylang is tested end to end on every change: a site in two languages with translated posts, pages and categories (`test/polylang.test.mjs`). WPML support is in beta: it uses WPML's public hooks, but WPML is a paid plugin, so no automated test runs it yet. Tell us if anything looks wrong.

The page itself speaks English or French, following the site's language.

## For developers

Filters:

| Filter | |
|---|---|
| `maisongr_sitemap_entries` | The addresses, after they're read. Each has `loc`, and optionally `lastmod` (`YYYY-MM-DD`), `lang`, `alternates` and `images`. |
| `maisongr_sitemap_post_types` | The post types listed. Default: every public type but attachments. |
| `maisongr_sitemap_taxonomies` | The taxonomies whose term archives are listed. Return `[]` to list none. |
| `maisongr_sitemap_theme` | The look: `name`, `light` palette, `fonts`, `icon`. |
| `maisongr_sitemap_credit` | Whether the page shows its "Made with maisongr-sitemap" line. Default `false`: a credit on the public site is the site owner's choice. |

The list is cached for an hour, and read again whenever content, the theme or the permalinks change.

### Working on the plugin

The plugin is plain PHP in [`maisongr-sitemap/`](maisongr-sitemap). Node is only for development:

```sh
npm ci
npm test       # boots WordPress in WebAssembly (Playground): no PHP or Docker needed
npm run assets # regenerates the page's stylesheet rules and script from maisongr-sitemap
npm run zip    # dist/maisongr-sitemap.zip, ready to upload
```

`WP_PHP=7.4 WP_VERSION=6.5 npm test` runs the tests on other versions.

## Contributing

Issues and pull requests are welcome: see [CONTRIBUTING.md](CONTRIBUTING.md), and report security problems privately ([SECURITY.md](SECURITY.md)).

## More from Maison GR

Open source from [Maison GR](https://www.maisongr.com), a software studio in Québec City:

- [maisongr-sitemap](https://github.com/maison-gr/maisongr-sitemap): one sitemap, two readers. The XML search engines need, and a page in your site's own design for people.
- [maisongr-skills](https://github.com/maison-gr/maisongr-skills): one engineering standard for every AI coding agent: Claude Code, Codex, Cursor, Copilot, Gemini CLI.
- [maisongr-typo](https://github.com/maison-gr/maisongr-typo): French typography, the Québec way, with a CI check.
- [maisongr-law25](https://github.com/maison-gr/maisongr-law25): your Law 25 privacy impact assessment, next to your code, checked in CI.

## License

[MIT](LICENSE) © [Maison GR Inc.](https://www.maisongr.com)
