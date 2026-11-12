// The plugin in a real WordPress (Playground: PHP in WebAssembly, no PHP or Docker needed).
// 1. Parity: the PHP page, stylesheet and XML match the maisongr-sitemap package byte for byte.
// 2. The site: /sitemap.xml lists the right addresses, answers browsers and crawlers apart,
//    and replaces WordPress's own sitemap.
import assert from "node:assert/strict";
import { spawn } from "node:child_process";
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from "node:fs";
import { createRequire } from "node:module";
import { tmpdir } from "node:os";
import { dirname, join } from "node:path";
import { after, before, describe, it } from "node:test";
import { fileURLToPath } from "node:url";
import { createSitemap, indexPage, indexXml, pageCss, urlsetPage, urlsetXml } from "maisongr-sitemap";

const root = join(dirname(fileURLToPath(import.meta.url)), "..");
const posix = (p) => p.split("\\").join("/");
const PORT = 9400 + Math.floor(Math.random() * 400);
const BASE = `http://127.0.0.1:${PORT}`;
let server;
/** The folder holding the run's blueprint, removed when the tests end. */
let scratch;

/**
 * The test site's blueprint, on the PHP and WordPress versions asked for (WP_PHP=7.4 WP_VERSION=6.5 npm test).
 * They go in the blueprint itself: its defaults win over the command line's --php and --wp.
 */
function blueprint() {
  const steps = JSON.parse(readFileSync(join(root, "test", "blueprint.json"), "utf8"));
  scratch = mkdtempSync(join(tmpdir(), "mgr-wp-"));
  const file = join(scratch, "blueprint.json");
  writeFileSync(file, JSON.stringify({ ...steps, preferredVersions: { php: process.env.WP_PHP ?? "8.3", wp: process.env.WP_VERSION ?? "latest" } }));
  return file;
}

before(async () => {
  const cli = join(dirname(createRequire(import.meta.url).resolve("@wp-playground/cli/package.json")), "wp-playground.js");
  // prettier-ignore
  server = spawn(process.execPath, [
    cli, "server", "--port", String(PORT), "--verbosity", "quiet",
    "--mount-dir", posix(join(root, "maisongr-sitemap")), "/wordpress/wp-content/plugins/maisongr-sitemap",
    "--mount-dir", posix(join(root, "test", "harness")), "/wordpress/maisongr-test",
    "--mount-dir", posix(join(root, "test", "harness", "no-redirect.php")), "/internal/shared/mu-plugins/sitemap-redirect.php",
    "--mount-dir", posix(join(root, "test", "harness", "credit-opt-in.php")), "/internal/shared/mu-plugins/credit-opt-in.php",
    "--blueprint", posix(blueprint()),
  ], { stdio: ["ignore", "ignore", "pipe"] });
  let log = "";
  server.stderr.on("data", (d) => (log += d));
  // Ready once the plugin itself answers: WordPress serves pages before the blueprint has activated it.
  for (let i = 0; i < 180; i++) {
    const type = await fetch(`${BASE}/?maisongr_sitemap=1`).then((r) => r.headers.get("content-type"), () => null);
    if (type === "application/xml; charset=utf-8") return;
    if (server.exitCode !== null) throw new Error(`Playground stopped: ${log}`);
    await new Promise((r) => setTimeout(r, 1000));
  }
  throw new Error(`Playground did not start: ${log}`);
});

after(() => {
  server?.kill();
  if (scratch) rmSync(scratch, { recursive: true, force: true });
});

const browser = { "Sec-Fetch-Dest": "document", Accept: "text/html,application/xhtml+xml" };
const get = (path, headers = {}) => fetch(`${BASE}${path}`, { headers, redirect: "manual" });

describe("parity with the maisongr-sitemap package", () => {
  const theme = {
    name: "Atelier Nord",
    light: { background: "#fbfaf7", foreground: "#141414", muted: "#5c5c58", border: "#e3e1db", accent: "#2f6b4f", surface: "#ffffff" },
    dark: { background: "#121212", foreground: "#f2f2f2", muted: "#a3a3a3", border: "#2a2a2a", accent: "#7bc4a0", surface: "#1b1b1b" },
    fonts: [
      { role: "display", family: "Fraunces", files: [{ url: "/fonts/fraunces.woff2", weight: "400 700" }] },
      { role: "body", family: "Inter", files: [{ url: "/fonts/inter.woff2" }, { url: "/fonts/inter-italic.woff2", style: "italic" }] },
    ],
    icon: "/icon.png",
  };
  const site = "https://atelier.example";
  const pair = (path, fr, lastmod) => {
    const alternates = [
      { hreflang: "en", href: `${site}${path}` },
      { hreflang: "fr", href: `${site}${fr}` },
      { hreflang: "x-default", href: `${site}${path}` },
    ];
    return [
      { loc: `${site}${path}`, lang: "en", alternates, ...(lastmod ? { lastmod } : {}) },
      { loc: `${site}${fr}`, lang: "fr", alternates, ...(lastmod ? { lastmod } : {}) },
    ];
  };
  const entries = [
    ...pair("/", "/fr/", "2026-11-01"),
    ...pair("/about/", "/fr/a-propos/", "2026-10-12"),
    { loc: `${site}/journal/first-light/`, lastmod: "2026-09-30", images: [`${site}/img/a.jpg`] },
    { loc: `${site}/journal/rye-&-salt/`, lastmod: "2026-11-05" },
    { loc: `${site}/journal/caf%C3%A9-notes/` },
    { loc: `${site}/journal/winter-bread/`, lastmod: "2026-08-02" },
    { loc: `${site}/2026/` },
    { loc: `${site}/contact/`, lastmod: "2026-07-14" },
    { loc: `${site}/shop/"quote"/` },
    { loc: `${site}/shop/loaves/`, lastmod: "2026-11-09" },
    { loc: `${site}/privacy/` },
    { loc: `${site}/terms/` },
  ];
  const parts = [
    { loc: `${site}/sitemap.xml?part=1`, lastmod: "2026-11-09", count: 50000 },
    { loc: `${site}/sitemap.xml?part=2`, count: 12 },
  ];

  const php = async (body) => (await fetch(`${BASE}/maisongr-test/render.php`, { method: "POST", body: JSON.stringify(body) })).json();
  const xmlCss = async (t, lang) => {
    const handler = createSitemap({ manifest: { version: 1, framework: "wordpress", routes: [], theme: t }, lang });
    return (await handler(new Request(`${site}/sitemap.xml?asset=xml.css`))).text();
  };

  const cases = [
    ["English, with every feature", entries, theme, {}],
    ["French, without the credit", entries, theme, { lang: "fr_FR", credit: false, home: "/blog/" }],
    ["a theme without fonts, dark mode or icon", entries.slice(4, 8), { name: "Orbit", light: theme.light, fonts: [] }, { lang: "fr" }],
    ["no pages yet", [], theme, {}],
  ];

  for (const [name, list, t, options] of cases) {
    it(`renders the same page, stylesheet and XML: ${name}`, async () => {
      const out = await php({ entries: list, parts, theme: t, options });
      assert.equal(out.page, urlsetPage(list, t, options));
      assert.equal(out.index, indexPage(parts, t, options));
      assert.equal(out.css, pageCss(t));
      assert.equal(out.xmlCss, await xmlCss(t, options.lang));
      assert.equal(out.xml, urlsetXml(list, "/sitemap.xml?asset=xml.css"));
      assert.equal(out.indexXml, indexXml(parts, "/sitemap.xml?asset=xml.css"));
    });
  }
});

describe("the sitemap on a WordPress site", () => {
  it("runs on the WordPress version asked for", { skip: !process.env.WP_VERSION }, async () => {
    const generator = (await (await get("/")).text()).match(/<meta name="generator" content="WordPress ([\d.]+)/)?.[1];
    assert.ok(generator?.startsWith(process.env.WP_VERSION), `WordPress ${generator}`);
  });

  it("lists every public address, and leaves out noindex, password-protected and draft posts", async () => {
    const res = await get("/sitemap.xml");
    assert.equal(res.status, 200);
    assert.equal(res.headers.get("content-type"), "application/xml; charset=utf-8");
    assert.equal(res.headers.get("vary"), "Sec-Fetch-Dest");
    const xml = await res.text();
    const locs = [...xml.matchAll(/<loc>([^<]+)<\/loc>/g)].map((m) => m[1].replace(BASE, ""));
    for (const path of ["/", "/sourdough-starter/", "/rye-loaf/", "/focaccia/", "/about-us/", "/category/recipes/"]) assert.ok(locs.includes(path), `${path} is listed`);
    for (const path of ["/thank-you/", "/order-sent/", "/members/", "/draft-idea/"]) assert.ok(!locs.includes(path), `${path} is left out`);
    assert.equal(new Set(locs).size, locs.length, "each address once");
    assert.match(xml, /<lastmod>\d{4}-\d{2}-\d{2}<\/lastmod>/);
  });

  it("gives a browser the page, noindex and private, with the same addresses", async () => {
    const res = await get("/sitemap.xml", browser);
    assert.equal(res.headers.get("content-type"), "text/html; charset=utf-8");
    assert.equal(res.headers.get("x-robots-tag"), "noindex");
    assert.match(res.headers.get("cache-control"), /^private/);
    const html = await res.text();
    assert.match(html, /<h1>Every page on Fern &amp; Oak<span class="mark">\.<\/span><\/h1>/);
    const xml = await (await get("/sitemap.xml")).text();
    const paths = (s, re) => [...s.matchAll(re)].map((m) => m[1].replace(BASE, "")).sort();
    assert.deepEqual(paths(html, /class="url" href="([^"]+)"/g), paths(xml, /<loc>([^<]+)<\/loc>/g));
  });

  it("shows no credit link unless the site opts in (WordPress.org guideline 10)", async () => {
    assert.doesNotMatch(await (await get("/sitemap.xml", browser)).text(), /class="credit"/);
    assert.match(await (await get("/sitemap.xml?credit=1", browser)).text(), /<p class="credit">.*maisongr-sitemap/);
  });

  it("serves the page's stylesheet in the theme's colours and fonts, and its script", async () => {
    const css = await (await get("/sitemap.xml?asset=page.css")).text();
    // The default theme's own font: Manrope in Twenty Twenty-Five; older default themes set none in WOFF2 files.
    if (!process.env.WP_VERSION) assert.match(css, /@font-face\{font-family:"Manrope"/);
    assert.match(css, /^:root\{--bg:#[0-9A-Fa-f]{6};--fg:#[0-9A-Fa-f]{6};/m);
    assert.ok(!css.startsWith("/*"), "no banner comment");
    const js = await get("/sitemap.xml?asset=page.js");
    assert.equal(js.headers.get("content-type"), "text/javascript; charset=utf-8");
    assert.match(await js.text(), /^\(function \(\) \{/);
  });

  it("answers an unchanged sitemap with 304", async () => {
    const tag = (await get("/sitemap.xml")).headers.get("etag");
    assert.equal((await get("/sitemap.xml", { "If-None-Match": tag })).status, 304);
    assert.equal((await get("/sitemap.xml", { "If-None-Match": `W/${tag}` })).status, 304);
  });

  it("replaces WordPress's own sitemap and names this one in robots.txt", async () => {
    for (const path of ["/wp-sitemap.xml", "/wp-sitemap-posts-post-1.xml"]) {
      const res = await get(path);
      assert.equal(res.status, 301);
      assert.equal(res.headers.get("location"), `${BASE}/sitemap.xml`);
    }
    assert.match(await (await get("/robots.txt")).text(), new RegExp(`Sitemap: ${BASE}/sitemap\\.xml`));
  });

  it("answers a part that doesn't exist with 404", async () => {
    assert.equal((await get("/sitemap.xml?part=9")).status, 404);
  });
});
