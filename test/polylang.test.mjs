// Polylang, end to end: a site in English and French with translated posts, a page and a
// category. Each address names its language versions in the XML (hreflang and x-default),
// and the page shows the versions of one page on one row.
import assert from "node:assert/strict";
import { after, before, describe, it } from "node:test";
import { browser, startSite } from "./playground.mjs";

// Polylang 3.9.2 needs WordPress 6.8; the plugin's own oldest version (6.5) runs without it.
const [major, minor = 0] = (process.env.WP_VERSION ?? "").split(".").map(Number);
const skip = process.env.WP_VERSION && (major < 6 || (major === 6 && minor < 8)) ? `Polylang 3.9.2 needs WordPress 6.8 (this run: ${process.env.WP_VERSION})` : false;

let site;
before(async () => {
  if (skip) return;
  site = await startSite("polylang-blueprint.json", [
    ["harness/polylang-setup.php", "/wordpress/maisongr-test/polylang-setup.php"],
    ["harness/no-redirect.php", "/internal/shared/mu-plugins/sitemap-redirect.php"],
  ]);
  const setup = await (await site.get("/maisongr-test/polylang-setup.php")).text();
  assert.equal(setup, "ready", `Polylang setup: ${setup}`);
});
after(() => site?.stop());

/** Each <url> of the XML, by its path: its alternates as { hreflang: path }. */
async function urls() {
  const xml = await (await site.get("/sitemap.xml")).text();
  const path = (u) => u.replace(site.base, "");
  const out = new Map();
  for (const [, block] of xml.matchAll(/<url>([\s\S]*?)<\/url>/g)) {
    const loc = path(block.match(/<loc>([^<]+)<\/loc>/)[1]);
    const alternates = Object.fromEntries([...block.matchAll(/hreflang="([^"]+)" href="([^"]+)"/g)].map(([, lang, href]) => [lang, path(href)]));
    out.set(loc, alternates);
  }
  return out;
}

const translated = [
  ["the home page", "/", "/fr/"],
  ["a post", "/sourdough-starter/", "/fr/levain/"],
  ["a page", "/about-us/", "/fr/a-propos/"],
  ["a category", "/category/recipes/", "/fr/category/recettes/"],
];

describe("the sitemap on a Polylang site", { skip }, () => {
  for (const [what, en, fr] of translated) {
    it(`links both language versions of ${what}, with x-default on the default language`, async () => {
      const list = await urls();
      const both = { en, fr, "x-default": en };
      assert.deepEqual(list.get(en), both, `${en} names both versions`);
      assert.deepEqual(list.get(fr), both, `${fr} names both versions`);
    });
  }

  it("lists a post with no translation without alternates", async () => {
    assert.deepEqual((await urls()).get("/rye-loaf/"), {});
  });

  it("leaves out an unpublished translation, and its alternate", async () => {
    const list = await urls();
    assert.deepEqual(list.get("/focaccia/"), {});
    assert.ok(![...list.keys()].some((loc) => loc.includes("focaccia-maison")), "the draft is not listed");
  });

  it("shows the language versions of one page on one row", async () => {
    const html = await (await site.get("/sitemap.xml", browser)).text();
    const rows = [...html.matchAll(/<li class="row[^"]*"[^>]*>([\s\S]*?)<\/li>/g)].map((m) => m[1]);
    for (const [what, en, fr] of translated) {
      const row = rows.filter((r) => r.includes(`class="url" href="${en}" hreflang="en"`));
      assert.equal(row.length, 1, `one row for ${what}`);
      assert.match(row[0], new RegExp(`<a class="lang" href="${fr}" hreflang="fr">FR</a>`), `${what}'s row links its French version`);
      assert.ok(!rows.some((r) => r.includes(`class="url" href="${fr}"`)), `no row of its own for ${fr}`);
    }
  });
});
