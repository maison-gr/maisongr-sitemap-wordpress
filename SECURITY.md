# Security

## Reporting a problem

Please report security problems privately, through GitHub: **Security → Report a vulnerability** on this repository. Don't open a public issue.

Say what you found, how to reproduce it, and what it lets someone do. You'll get a reply within a week, and a fix or a plan once the problem is confirmed. Reports are credited in the release notes unless you'd rather not be named.

## Supported versions

Security fixes go into the latest release.

## What the plugin does that matters here

- **It reads** your published content (posts, pages, public custom post types, terms), your theme's global styles and fonts, your site's name and icon, and the robots meta of Yoast SEO, Rank Math, SEOPress and The SEO Framework. It never reads drafts, private or password-protected posts.
- **It stores** one cached list (a transient, kept an hour) and one option that marks its rewrite rules. Uninstalling removes both.
- **It serves** `/sitemap.xml` and its stylesheet and script at `?asset=`. Every value written into the XML, the page and its stylesheet is escaped, and colours and font names from the theme are checked against a strict pattern first. It reads the request headers `Sec-Fetch-Dest`, `Accept` and `If-None-Match`, and nothing else from the visitor.
- **It sends nothing anywhere**: no remote request, no tracking, no telemetry.
