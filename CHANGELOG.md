# Changelog

All notable changes to the Maison GR Sitemap WordPress plugin. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and versions follow [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [1.0.2] - 2026-11-25

### Fixed

- A translucent theme colour (`#rrggbbaa` or `#rgba`) was measured as if it were solid, so a faint grey could pass the AA check for the page's secondary text, and a pale accent the 3:1 check for focus rings. The contrast is now measured as the colour shows on the page background. WordPress 7.2's default theme writes its colours with alpha.

### Changed

- Tested up to WordPress 7.2 (checked on 7.2-RC3 with PHP 8.5) and on PHP 8.6: CI now runs the end-to-end tests on WordPress's next release, and loads and runs the plugin's own code on PHP 8.6.

## [1.0.1] - 2026-11-20

### Fixed

- With Jetpack's Sitemaps module on, `/sitemap.xml` kept serving Jetpack's XSLT sitemap, because Jetpack answers before the plugin can, and nothing said so. The admin notice now names Jetpack and where to turn its Sitemaps off. An end-to-end test boots a site with Jetpack 16.4 and checks both.
- The admin notice also names SEOPress (its `/sitemaps.xml`) and The SEO Framework (which serves `/sitemap.xml` too), with where to turn each sitemap off, and lists every SEO plugin it finds instead of the first one only. Checked end to end with SEOPress 10.4 and The SEO Framework 5.2.1.

### Changed

- Polylang support is no longer beta: an end-to-end test boots a Polylang site in two languages and checks every address's `hreflang` alternates, `x-default`, untranslated and unpublished posts, and the page's rows. WPML support stays in beta ([#1](https://github.com/maison-gr/maisongr-sitemap-wordpress/issues/1)).

## [1.0.0]

### Added

- `/sitemap.xml` with two readers: the XML for search engines, and a page in the theme's colours and fonts for people, told apart by `Sec-Fetch-Dest` and `Accept`. The page matches the maisongr-sitemap package's byte for byte (from 1.7.0's design).
- Every public address: the home page, published posts, pages and public custom post types dated from their last edit, term archives in use, and featured images.
- Pages marked noindex by Yoast SEO, Rank Math, SEOPress or The SEO Framework, and password-protected posts, left out.
- WordPress's own sitemap turned off, its addresses redirected with a 301; `robots.txt` names the new one; a notice when an SEO plugin makes a second sitemap.
- `ETag` and `304 Not Modified`; an index of `?part=N` files past 50,000 addresses.
- Polylang and WPML translations linked with hreflang (beta).
- Filters: `maisongr_sitemap_entries`, `maisongr_sitemap_post_types`, `maisongr_sitemap_taxonomies`, `maisongr_sitemap_theme`, `maisongr_sitemap_credit` (the page's credit line, off unless the site turns it on).
