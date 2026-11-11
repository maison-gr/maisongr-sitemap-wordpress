// dist/maisongr-sitemap.zip: the plugin folder as committed, ready to upload in WordPress
// (Plugins, Add New Plugin, Upload Plugin) or to attach to a GitHub release.
// git archive takes only committed files, so nothing local slips in.
import { execFileSync } from "node:child_process";
import { mkdirSync } from "node:fs";

mkdirSync(new URL("../dist", import.meta.url), { recursive: true });
execFileSync("git", ["archive", "--format=zip", "--prefix=maisongr-sitemap/", "-o", "dist/maisongr-sitemap.zip", "HEAD:maisongr-sitemap"], { stdio: "inherit" });
console.log("dist/maisongr-sitemap.zip written from HEAD");
