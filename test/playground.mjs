// Starts a WordPress site in Playground (PHP in WebAssembly, no PHP or Docker needed) with the
// plugin mounted, from one of test/'s blueprints. Shared by the test files.
import { spawn } from "node:child_process";
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from "node:fs";
import { createRequire } from "node:module";
import { tmpdir } from "node:os";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";

export const root = join(dirname(fileURLToPath(import.meta.url)), "..");
const posix = (p) => p.split("\\").join("/");

/**
 * Boots the site and resolves once the plugin itself answers: WordPress serves pages before the
 * blueprint has activated it. The PHP and WordPress versions asked for (WP_PHP=7.4 WP_VERSION=6.5
 * npm test) go in the blueprint itself: its defaults win over the command line's --php and --wp.
 *
 * @param {string} name The blueprint's file in test/.
 * @param {[string, string][]} mounts Extra [file or folder in test/, path in the site] pairs.
 */
export async function startSite(name, mounts = []) {
  const port = 9400 + Math.floor(Math.random() * 400);
  const base = `http://127.0.0.1:${port}`;
  const steps = JSON.parse(readFileSync(join(root, "test", name), "utf8"));
  const scratch = mkdtempSync(join(tmpdir(), "mgr-wp-"));
  const blueprint = join(scratch, "blueprint.json");
  writeFileSync(blueprint, JSON.stringify({ ...steps, preferredVersions: { php: process.env.WP_PHP ?? "8.3", wp: process.env.WP_VERSION ?? "latest" } }));

  const cli = join(dirname(createRequire(import.meta.url).resolve("@wp-playground/cli/package.json")), "wp-playground.js");
  const mountArgs = [["maisongr-sitemap", "/wordpress/wp-content/plugins/maisongr-sitemap", root], ...mounts.map(([from, to]) => [from, to, join(root, "test")])].flatMap(
    ([from, to, dir]) => ["--mount-dir", posix(join(dir, from)), to],
  );
  // prettier-ignore
  const server = spawn(process.execPath, [cli, "server", "--port", String(port), "--verbosity", "quiet", ...mountArgs, "--blueprint", posix(blueprint)], {
    stdio: ["ignore", "ignore", "pipe"],
  });
  const stop = () => {
    server.kill();
    rmSync(scratch, { recursive: true, force: true });
  };
  let log = "";
  server.stderr.on("data", (d) => (log += d));
  for (let i = 0; i < 240; i++) {
    const type = await fetch(`${base}/?maisongr_sitemap=1`).then((r) => r.headers.get("content-type"), () => null);
    if (type === "application/xml; charset=utf-8") {
      return { base, stop, get: (path, headers = {}) => fetch(`${base}${path}`, { headers, redirect: "manual" }) };
    }
    if (server.exitCode !== null) {
      stop();
      throw new Error(`Playground stopped: ${log}`);
    }
    await new Promise((r) => setTimeout(r, 1000));
  }
  stop();
  throw new Error(`Playground did not start: ${log}`);
}

/** What a browser sends when it opens the sitemap. */
export const browser = { "Sec-Fetch-Dest": "document", Accept: "text/html,application/xhtml+xml" };
