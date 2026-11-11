// The page's stylesheet rules and script, taken from the maisongr-sitemap package, so the
// WordPress page and the package's page share one design. Run after updating the package:
//   npm run assets
// The theme-dependent head of the stylesheet (fonts, colours) is built in PHP; the rules
// after it are the same for every site, and are what this writes.
import { writeFileSync } from "node:fs";
import { createSitemap } from "maisongr-sitemap";

const handler = createSitemap({ site: "https://example.com", entries: () => [] });
const get = async (asset) => (await handler(new Request(`https://example.com/sitemap.xml?asset=${asset}`))).text();

const css = await get("page.css");
const start = css.indexOf("*,*::before,*::after");
if (start === -1) throw new Error("The package's stylesheet changed shape: page-rules.css can't be cut from it.");
const banner = (what) => `/* ${what}, generated from maisongr-sitemap by scripts/assets.mjs. Do not edit. */\n`;

writeFileSync(new URL("../maisongr-sitemap/assets/page-rules.css", import.meta.url), banner("The sitemap page's rules") + css.slice(start));
writeFileSync(new URL("../maisongr-sitemap/assets/page.js", import.meta.url), banner("The sitemap page's filter") + (await get("page.js")));
console.log("assets: page-rules.css and page.js written");
