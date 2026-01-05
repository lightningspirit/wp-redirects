#!/usr/bin/env node
/**
 * Generate wp-info.json + bump versions for a WordPress plugin.
 *
 * - Parses plugin header from main PHP file
 * - Parses readme.txt metadata + sections + changelog
 * - Writes wp-info.json
 * - Optional bump: updates Version (php header) + Stable tag (readme.txt) + README.md if present
 *
 * Usage:
 *   node tools/wp-info.mjs generate --main=./redirects.php --readme=./readme.txt --out=./wp-info.json
 *   node tools/wp-info.mjs bump --version=1.2.0 --main=./redirects.php --readme=./readme.txt --readmemd=./README.md --out=./wp-info.json
 */

import fs from "node:fs";
import { marked } from "marked";

function readFile(p) {
  return fs.readFileSync(p, "utf8");
}
function writeFile(p, s) {
  fs.writeFileSync(p, s, "utf8");
}

function parseArgs(argv) {
  const args = { _: [] };
  for (const a of argv.slice(2)) {
    if (a.startsWith("--")) {
      const [k, ...rest] = a.slice(2).split("=");
      args[k] = rest.join("=") || true;
    } else {
      args._.push(a);
    }
  }
  return args;
}

function formatUtcNow() {
  return new Date().toISOString().replace('T', ' ').replace('Z', '');
}

function escapeHtml(s) {
  return String(s)
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}

function parseWpPluginHeader(phpContent) {
  // Extract the first /** ... */ block and parse "Key: Value" pairs inside it.
  const m = phpContent.match(/\/\*\*[\s\S]*?\*\//);
  if (!m) return {};
  const block = m[0];
  const lines = block.split("\n").map((l) => l.replace(/^\s*\*\s?/, "").trim());
  const kv = {};
  for (const line of lines) {
    const mm = line.match(/^([A-Za-z0-9 _-]+):\s*(.+)$/);
    if (mm) kv[mm[1].trim()] = mm[2].trim();
  }
  return kv;
}

function parseReadmeTxt(readmeContent) {
  // Parse top headers and sections
  const meta = {};
  const headerLines = readmeContent.split("\n").slice(0, 50);
  for (const line of headerLines) {
    const mm = line.match(/^([A-Za-z0-9 _-]+):\s*(.+)\s*$/);
    if (mm) meta[mm[1].trim()] = mm[2].trim();
  }

  function section(name) {
    // == Name == until next == ... ==
    const re = new RegExp(`==\\s*${name}\\s*==([\\s\\S]*?)(?=\\n==\\s*[^=]+\\s*==|\\s*$)`, "i");
    const m = readmeContent.match(re);
    return m ? m[1].trim() : "";
  }

  return {
    meta,
    sections: {
      description: section("Description"),
      installation: section("Installation"),
      changelog: section("Changelog"),
    },
  };
}

function readExistingWpInfo(outPath) {
  if (!fs.existsSync(outPath)) return {};
  try {
    return JSON.parse(fs.readFileSync(outPath, "utf8"));
  } catch {
    return {};
  }
}

function changelogTxtToHtml(changelogTxt) {
  // readme.txt changelog is typically:
  // = 1.2.0 =
  // * Item
  // Convert to: <h4>1.2.0</h4><ol><li>Item</li></ol>...
  const lines = changelogTxt.split("\n");
  const out = [];
  let currentVer = null;
  let items = [];

  function flush() {
    if (!currentVer) return;
    out.push(`<h4>${escapeHtml(currentVer)}</h4>`);
    if (items.length) {
      out.push("<ol>");
      for (const it of items) out.push(`<li>${escapeHtml(it)}</li>`);
      out.push("</ol>");
    } else {
      out.push("<ol></ol>");
    }
  }

  for (const raw of lines) {
    const line = raw.trim();
    if (!line) continue;

    const ver = line.match(/^=\s*([0-9]+(?:\.[0-9]+){0,3})\s*=$/);
    if (ver) {
      flush();
      currentVer = ver[1];
      items = [];
      continue;
    }

    // bullets: "* ..." or "- ..."
    const bullet = line.match(/^[\*\-]\s+(.+)$/);
    if (bullet) items.push(bullet[1].trim());
  }

  flush();
  return out.join("");
}

function normalizeSectionTextToHtml(text) {
  // Keep it minimal: paragraph + line breaks. You can enhance later.
  // WordPress plugin update servers typically accept HTML.
  const escaped = escapeHtml(text);
  return marked.parse(escaped, {
    breaks: true,
  }).replace(/\n/g, "");
}

function buildWpInfo({ mainPhpPath, readmePath, outPath, overrides = {} }) {
  const php = readFile(mainPhpPath);
  const header = parseWpPluginHeader(php);

  const existing = readExistingWpInfo(outPath);

  const readme = readFile(readmePath);
  const { meta, sections } = parseReadmeTxt(readme);

  const name =
    overrides.name ||
    header["Plugin Name"] ||
    meta["=== "] || // unlikely
    "Plugin";

  const version =
    overrides.version ||
    header["Version"] ||
    meta["Stable tag"] ||
    "0.0.0";

  const authorName = overrides.author_name || header["Author"] || meta["Contributors"] || existing.author || "";
  const authorProfile = overrides.author_profile || header["Author URI"] || existing.author_profile || "";
  const homepage = overrides.homepage || header["Plugin URI"] || existing.homepage || "";

  const requires = overrides.requires || meta["Requires at least"] || header["Requires at least"] || "";
  const tested = overrides.tested || meta["Tested up to"] || header["Tested up to"] || "";
  const requiresPhp = overrides.requires_php || meta["Requires PHP"] || header["Requires PHP"] || "";

  const supportUrl =
    overrides.support_url || existing.support_url ||
    (homepage ? homepage.replace(/\/$/, "") + "/issues" : "");

  const downloadLink =
    overrides.download_link || existing.download_link ||
    (homepage
      ? homepage.replace(/\/$/, "") + "/archive/refs/heads/main.zip"
      : "");

  const banners = overrides.banners || existing.banners || {};

  const info = {
    name: name,
    version: version,
    author: authorProfile
      ? `<a href='${authorProfile}'>${escapeHtml(authorName || authorProfile)}</a>`
      : escapeHtml(authorName),
    author_profile: authorProfile,
    homepage: homepage || existing.homepage || "",
    download_link: downloadLink || existing.download_link || "",
    support_url: supportUrl || existing.support_url || "",
    requires: requires,
    tested: tested,
    requires_php: requiresPhp,
    last_updated: overrides.last_updated || formatUtcNow(),
    sections: {
      description: overrides.description_html || normalizeSectionTextToHtml(sections.description),
      installation: overrides.installation_html || normalizeSectionTextToHtml(sections.installation),
      changelog: overrides.changelog_html || changelogTxtToHtml(sections.changelog),
    },
    banners: {
      low: banners.low || (homepage ? `${homepage.replace(/\/$/, "")}/raw/main/.wordpress-org/banner-772x250.jpg` : ""),
      high: banners.high || (homepage ? `${homepage.replace(/\/$/, "")}/raw/main/.wordpress-org/banner-1544x500.jpg` : ""),
    },
  };

  writeFile(outPath, JSON.stringify(info, null, 2) + "\n");
  return info;
}

function bumpVersionInMainPhp(mainPhpPath, newVersion) {
  const php = readFile(mainPhpPath);
  // Replace "Version: x"
  const updated = php.replace(
    /(Version:\s*)([0-9]+(?:\.[0-9]+){0,3})/i,
    `$1${newVersion}`
  );
  if (updated === php) throw new Error(`Could not find "Version:" in ${mainPhpPath}`);
  writeFile(mainPhpPath, updated);
}

function bumpStableTagInReadme(readmePath, newVersion) {
  const txt = readFile(readmePath);
  const updated = txt.replace(
    /^(Stable tag:\s*)(.+)$/mi,
    `$1${newVersion}`
  );
  if (updated === txt) throw new Error(`Could not find "Stable tag:" in ${readmePath}`);
  writeFile(readmePath, updated);
}

function bumpReadmeMdVersion(readmeMdPath, newVersion) {
  if (!readmeMdPath) return;
  if (!fs.existsSync(readmeMdPath)) return;

  const md = readFile(readmeMdPath);

  // Common patterns:
  // - "### v1.2.0" in changelog
  // - badges don't include version usually
  // We'll do a conservative replacement of "### vX" headings if present.
  const updated = md.replace(
    /(^###\s+v)([0-9]+(?:\.[0-9]+){0,3})/gmi,
    `$1${newVersion}`
  );

  writeFile(readmeMdPath, updated);
}

function ensureChangelogEntry(readmePath, version) {
  // Optionally ensures there is a changelog header like "= 1.2.0 =" present.
  // If not present, it inserts it at the top of the Changelog section.
  const txt = readFile(readmePath);
  const has = new RegExp(`^=\\s*${version.replace(/\./g, "\\.")}\\s*=$`, "m").test(txt);
  if (has) return;

  const re = /(==\s*Changelog\s*==\s*\n)/i;
  if (!re.test(txt)) throw new Error(`Could not find "== Changelog ==" in ${readmePath}`);

  const inserted = txt.replace(re, `$1\n= ${version} =\n* (TBD)\n\n`);
  writeFile(readmePath, inserted);
}

async function main() {
  const args = parseArgs(process.argv);
  const cmd = args._[0];

  const mainPhpPath = args.main || "./redirects.php";
  const readmePath = args.readme || "./readme.txt";
  const outPath = args.out || "./wp-info.json";
  const readmeMdPath = args.readmemd || "./README.md";

  if (!cmd || (cmd !== "generate" && cmd !== "bump")) {
    console.error("Usage:");
    console.error("  node tools/wp-info.mjs generate --main=... --readme=... --out=...");
    console.error("  node tools/wp-info.mjs bump --version=1.2.0 --main=... --readme=... --readmemd=... --out=...");
    process.exit(1);
  }

  if (cmd === "bump") {
    const newVersion = args.version;
    if (!newVersion) {
      console.error("Missing --version=x.y.z");
      process.exit(1);
    }

    bumpVersionInMainPhp(mainPhpPath, newVersion);
    bumpStableTagInReadme(readmePath, newVersion);

    // Optional: ensure changelog has an entry for this version.
    if (args.ensure_changelog) {
      ensureChangelogEntry(readmePath, newVersion);
    }

    // Optional: best-effort update for README.md (does nothing harmful if patterns not found)
    bumpReadmeMdVersion(readmeMdPath, newVersion);
  }

  const overrides = {
    // You can hardcode or pass as flags later if you want:
    // author_profile: "https://github.com/lightningspirit",
    // homepage: "https://github.com/lightningspirit/wp-redirects",
    // support_url: ".../issues",
    // download_link: ".../archive/refs/heads/main.zip",
  };

  const info = buildWpInfo({ mainPhpPath, readmePath, outPath, overrides });
  console.log(`Wrote ${outPath}`);
  console.log(`name=${info.name} version=${info.version} last_updated=${info.last_updated}`);
}

main().catch((e) => {
  console.error(e?.stack || String(e));
  process.exit(1);
});
