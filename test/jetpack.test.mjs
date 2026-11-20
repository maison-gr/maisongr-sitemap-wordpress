// Jetpack's Sitemaps module answers /sitemap.xml itself, early in the request (wp_loaded), before
// this plugin can, and its sitemap is styled with XSLT. Two sitemaps confuse search engines, so the
// plugin names Jetpack in its admin notice, as it does for Yoast SEO, Rank Math and All in One SEO.
import assert from "node:assert/strict";
import { after, before, describe, it } from "node:test";
import { startSite } from "./playground.mjs";

// Jetpack 16.4 needs WordPress 7.0; the plugin's own oldest version (6.5) runs without it.
const [major] = (process.env.WP_VERSION ?? "").split(".").map(Number);
const skip = process.env.WP_VERSION && major < 7 ? `Jetpack 16.4 needs WordPress 7.0 (this run: ${process.env.WP_VERSION})` : false;

let site;
before(async () => {
  if (skip) return;
  site = await startSite("jetpack-blueprint.json", [
    ["harness/jetpack-offline.php", "/internal/shared/mu-plugins/jetpack-offline.php"],
    ["harness/no-redirect.php", "/internal/shared/mu-plugins/sitemap-redirect.php"],
    ["harness/notice.php", "/wordpress/maisongr-test/notice.php"],
  ]);
});
after(() => site?.stop());

describe("next to Jetpack's sitemap", { skip }, () => {
  it("tells the administrator to turn off Jetpack's Sitemaps", async () => {
    const { notice, jetpack } = await (await site.get("/maisongr-test/notice.php")).json();
    assert.equal(jetpack, true, "Jetpack's Sitemaps module is on");
    assert.match(notice, /Jetpack makes a sitemap too/);
  });

  // Why the notice exists. If a Jetpack release stops answering first, this fails, and the notice's wording can change.
  it("is answered by Jetpack's XSLT sitemap while Jetpack's Sitemaps stay on", async () => {
    const xml = await (await site.get("/sitemap.xml")).text();
    assert.match(xml, /generator='jetpack-/);
    assert.match(xml, /<\?xml-stylesheet type="text\/xsl"/);
  });
});
