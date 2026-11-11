=== Maison GR Sitemap ===
Contributors: fadhlaouir
Tags: sitemap, xml sitemap, seo, google, search engines
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: MIT
License URI: https://opensource.org/licenses/MIT

One sitemap, two readers: the XML search engines need, and a page in your theme's own colours and type for people.

== Description ==

Search engines get the XML they need at `/sitemap.xml`. A person who opens the same address gets a page set in your theme's own colours and fonts, with every page listed, grouped by section and searchable.

**What it lists**

* The home page, and every published post, page and public custom post type, each dated from its last edit.
* The archive of each category, tag and public taxonomy term in use.
* Each post's featured image, for image search.

**What it leaves out**

* Pages an SEO plugin marks noindex: Yoast SEO, Rank Math, SEOPress and The SEO Framework.
* Password-protected, private and draft posts, and attachment pages.

**How the two readers are told apart**

A browser opening a page sends `Sec-Fetch-Dest: document` and asks for HTML; crawlers send neither. Those two headers decide which answer is served. No user agent is read, and both answers list exactly the same addresses. The page is noindex and cached privately.

**What it changes**

* WordPress's own sitemap (`/wp-sitemap.xml`) is turned off and redirects to `/sitemap.xml`. Deactivating the plugin brings it back.
* `robots.txt` names the sitemap.
* If Yoast SEO, Rank Math or All in One SEO also makes a sitemap, a notice says how to turn theirs off.

**Languages (beta)**

With Polylang or WPML, translations are linked with hreflang, including x-default. The page speaks English or French, following the site's language.

The plugin is open source: [github.com/maison-gr/maisongr-sitemap-wordpress](https://github.com/maison-gr/maisongr-sitemap-wordpress).

== Installation ==

1. Install and activate the plugin.
2. Open `/sitemap.xml` on your site: a browser shows the page, search engines get the XML.
3. Submit `https://your-site/sitemap.xml` in Google Search Console and Bing Webmaster Tools.

== Frequently Asked Questions ==

= Does it replace the sitemap from my SEO plugin? =

Use one sitemap. If Yoast SEO, Rank Math or All in One SEO also makes one, the plugin shows a notice saying where to turn theirs off.

= Can I leave out a post type or taxonomy? =

Yes, with the `maisongr_sitemap_post_types` and `maisongr_sitemap_taxonomies` filters. `maisongr_sitemap_entries` changes the final list.

= Does the page link to the plugin's authors? =

Not unless you choose to. To show a "Made with maisongr-sitemap" line at the foot of the page, add `add_filter( 'maisongr_sitemap_credit', '__return_true' );`.

= Is it cloaking to show people a page and crawlers XML? =

No. Both list exactly the same addresses: one list presented two ways. The page is noindex.

== Screenshots ==

1. The sitemap page in Twenty Twenty-Five: every page, grouped by section, with a filter and a newest-first sort.

== Changelog ==

= 1.0.0 =
* First release.
