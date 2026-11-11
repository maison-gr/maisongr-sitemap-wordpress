# Changelog

All notable changes to the Maison GR Sitemap WordPress plugin. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and versions follow [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [1.0.0]

### Added

- `/sitemap.xml` with two readers: the XML for search engines, and a page in the theme's colours and fonts for people, told apart by `Sec-Fetch-Dest` and `Accept`. The page matches the maisongr-sitemap package's byte for byte (from 1.7.0's design).
- Every public address: the home page, published posts, pages and public custom post types dated from their last edit, term archives in use, and featured images.
- Pages marked noindex by Yoast SEO, Rank Math, SEOPress or The SEO Framework, and password-protected posts, left out.
- WordPress's own sitemap turned off, its addresses redirected with a 301; `robots.txt` names the new one; a notice when an SEO plugin makes a second sitemap.
- `ETag` and `304 Not Modified`; an index of `?part=N` files past 50,000 addresses.
- Polylang and WPML translations linked with hreflang (beta).
- Filters: `maisongr_sitemap_entries`, `maisongr_sitemap_post_types`, `maisongr_sitemap_taxonomies`, `maisongr_sitemap_theme`, `maisongr_sitemap_credit` (the page's credit line, off unless the site turns it on).
