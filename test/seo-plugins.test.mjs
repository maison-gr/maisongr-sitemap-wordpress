// SEOPress and The SEO Framework make their own XSLT sitemaps (SEOPress at /sitemaps.xml, The SEO
// Framework at /sitemap.xml, this plugin's own address). Two sitemaps confuse search engines, so the
// admin notice names each SEO plugin that makes one, and says where to turn it off.
import assert from "node:assert/strict";
import { after, before, describe, it } from "node:test";
import { startSite } from "./playground.mjs";

// The SEO Framework 5.2.1 needs WordPress 7.0; the plugin's own oldest version (6.5) runs without it.
const [major] = (process.env.WP_VERSION ?? "").split(".").map(Number);
const skip = process.env.WP_VERSION && major < 7 ? `The SEO Framework 5.2.1 needs WordPress 7.0 (this run: ${process.env.WP_VERSION})` : false;

let site;
before(async () => {
  if (skip) return;
  site = await startSite("seo-plugins-blueprint.json", [
    ["harness/no-redirect.php", "/internal/shared/mu-plugins/sitemap-redirect.php"],
    ["harness/notice.php", "/wordpress/maisongr-test/notice.php"],
  ]);
});
after(() => site?.stop());

describe("next to SEOPress and The SEO Framework", { skip }, () => {
  it("names both, each with where to turn its sitemap off", async () => {
    const { notice } = await (await site.get("/maisongr-test/notice.php")).json();
    assert.match(notice, /SEOPress makes a sitemap too\. Turn off Enable XML Sitemap \(SEO, XML - HTML Sitemap\)/);
    assert.match(notice, /The SEO Framework makes a sitemap too, at the same address\. Turn off "Output optimized sitemap\?" \(SEO, Settings, Sitemap Settings\)/);
  });
});
