#!/usr/bin/env php
<?php
/**
 * Generate wp-info.json + bump versions for a WordPress plugin (PHP version).
 *
 * Usage:
 *   php tools/wp-info.php generate --main=./redirects.php --readme=./readme.txt --out=./wp-info.json
 *   php tools/wp-info.php bump --version=1.2.0 --main=./redirects.php --readme=./readme.txt --readmemd=./README.md --out=./wp-info.json
 *
 * Optional:
 *   --ensure_changelog=1  Ensures a changelog header "= x.y.z =" exists; inserts "(TBD)" entry if missing.
 */

declare(strict_types=1);

$autoloadCandidates = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../vendor/autoload.php',
];

$autoloaded = false;
foreach ($autoloadCandidates as $p) {
    if (is_file($p)) {
        require_once $p;
        $autoloaded = true;
        break;
    }
}

if (!$autoloaded) {
    fwrite(STDERR, "Composer autoload.php not found. Run: composer install\n");
    exit(1);
}

use \League\CommonMark\CommonMarkConverter;

function makeMarkdownConverter(): CommonMarkConverter
{
    return new CommonMarkConverter([
        'renderer' => [
            'soft_break' => "<br />\n",
        ],
    ]);
}

function readFileStr(string $path): string
{
    $s = @file_get_contents($path);
    if ($s === false) {
        throw new RuntimeException("Failed to read file: {$path}");
    }
    return $s;
}

function writeFileStr(string $path, string $content): void
{
    $ok = @file_put_contents($path, $content);
    if ($ok === false) {
        throw new RuntimeException("Failed to write file: {$path}");
    }
}

function parseArgs(array $argv): array
{
    $args = ['_' => []];
    foreach (array_slice($argv, 1) as $a) {
        if (str_starts_with($a, '--')) {
            $kv = substr($a, 2);
            $pos = strpos($kv, '=');
            if ($pos === false) {
                $args[$kv] = true;
            } else {
                $k = substr($kv, 0, $pos);
                $v = substr($kv, $pos + 1);
                $args[$k] = $v;
            }
        } else {
            $args['_'][] = $a;
        }
    }
    return $args;
}

function formatUtcNow(): string
{
    // Matches your "YYYY-MM-DD HH:mm:ss" without timezone suffix.
    return gmdate('Y-m-d H:i:s');
}

function escapeHtml(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function parseWpPluginHeader(string $phpContent): array
{
    // Extract the first /** ... */ block.
    if (!preg_match('/\/\*\*[\s\S]*?\*\//', $phpContent, $m)) {
        return [];
    }

    $block = $m[0];
    $lines = preg_split("/\R/", $block) ?: [];
    $kv = [];

    foreach ($lines as $line) {
        $line = preg_replace('/^\s*\*\s?/', '', (string) $line);
        $line = trim((string) $line);

        if (preg_match('/^([A-Za-z0-9 _-]+):\s*(.+)$/', $line, $mm)) {
            $k = trim($mm[1]);
            $v = trim($mm[2]);
            $kv[$k] = $v;
        }
    }

    return $kv;
}

function parseReadmeTxt(string $readmeContent): array
{
    $meta = [];
    $lines = preg_split("/\R/", $readmeContent) ?: [];
    $headerLines = array_slice($lines, 0, 50);

    foreach ($headerLines as $line) {
        if (preg_match('/^([A-Za-z0-9 _-]+):\s*(.+)\s*$/', $line, $mm)) {
            $meta[trim($mm[1])] = trim($mm[2]);
        }
    }

    $section = function (string $name) use ($readmeContent): string {
        $pattern = '/==\s*' . preg_quote($name, '/') . '\s*==([\s\S]*?)(?=\R==\s*[^=]+\s*==|\s*$)/i';
        if (preg_match($pattern, $readmeContent, $m)) {
            return trim((string) $m[1]);
        }
        return '';
    };

    return [
        'meta' => $meta,
        'sections' => [
            'description' => $section('Description'),
            'installation' => $section('Installation'),
            'changelog' => $section('Changelog'),
        ],
    ];
}

function readExistingWpInfo(string $outPath): array
{
    if (!file_exists($outPath))
        return [];
    try {
        $raw = readFileStr($outPath);
        $j = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        return is_array($j) ? $j : [];
    } catch (Throwable $e) {
        return [];
    }
}

function changelogTxtToHtml(string $changelogTxt): string
{
    // readme.txt changelog usually:
    // = 1.2.0 =
    // * Item
    // Convert to: <h4>1.2.0</h4><ol><li>Item</li></ol>...
    $lines = preg_split("/\R/", $changelogTxt) ?: [];
    $out = [];
    $currentVer = null;
    $items = [];

    $flush = function () use (&$out, &$currentVer, &$items): void {
        if ($currentVer === null)
            return;
        $out[] = '<h4>' . escapeHtml($currentVer) . '</h4>';
        $out[] = '<ol>';
        foreach ($items as $it) {
            $out[] = '<li>' . escapeHtml($it) . '</li>';
        }
        $out[] = '</ol>';
    };

    foreach ($lines as $raw) {
        $line = trim((string) $raw);
        if ($line === '')
            continue;

        if (preg_match('/^=\s*([0-9]+(?:\.[0-9]+){0,3})\s*=$/', $line, $m)) {
            $flush();
            $currentVer = $m[1];
            $items = [];
            continue;
        }

        if (preg_match('/^[\*\-]\s+(.+)$/', $line, $m)) {
            $items[] = trim($m[1]);
        }
    }

    $flush();
    return implode('', $out);
}

function normalizeSectionTextToHtml(string $text, CommonMarkConverter $md): string
{
    $text = trim($text);
    if ($text === '')
        return '';

    // CommonMark expects raw Markdown; do NOT pre-escape.
    // It outputs HTML; we'll trim and keep it compact.
    $html = (string) $md->convert($text);

    // Optional: remove trailing newlines for stable JSON diffs
    return preg_replace("/\R+$/", "", $html) ?? $html;
}

function buildWpInfo(array $opts): array
{
    $md = makeMarkdownConverter();

    $mainPhpPath = $opts['mainPhpPath'];
    $readmePath = $opts['readmePath'];
    $outPath = $opts['outPath'];
    $overrides = $opts['overrides'] ?? [];

    $php = readFileStr($mainPhpPath);
    $header = parseWpPluginHeader($php);

    $existing = readExistingWpInfo($outPath);

    $readme = readFileStr($readmePath);
    $parsed = parseReadmeTxt($readme);
    $meta = $parsed['meta'];
    $sections = $parsed['sections'];

    $name = $overrides['name']
        ?? $header['Plugin Name']
        ?? 'Plugin';

    $version = $overrides['version']
        ?? $header['Version']
        ?? ($meta['Stable tag'] ?? null)
        ?? '0.0.0';

    $authorName = $overrides['author_name']
        ?? ($header['Author'] ?? null)
        ?? ($meta['Contributors'] ?? null)
        ?? ($existing['author'] ?? '');

    $authorProfile = $overrides['author_profile']
        ?? ($header['Author URI'] ?? null)
        ?? ($existing['author_profile'] ?? '');

    $homepage = $overrides['homepage']
        ?? ($header['Plugin URI'] ?? null)
        ?? ($existing['homepage'] ?? '');

    $requires = $overrides['requires']
        ?? ($meta['Requires at least'] ?? null)
        ?? ($header['Requires at least'] ?? null)
        ?? '';

    $tested = $overrides['tested']
        ?? ($meta['Tested up to'] ?? null)
        ?? ($header['Tested up to'] ?? null)
        ?? '';

    $requiresPhp = $overrides['requires_php']
        ?? ($meta['Requires PHP'] ?? null)
        ?? ($header['Requires PHP'] ?? null)
        ?? '';

    // Preserve existing static fields by default
    $supportUrl = $overrides['support_url']
        ?? ($existing['support_url'] ?? null)
        ?? ($homepage ? rtrim($homepage, '/') . '/issues' : '');

    $downloadLink = $overrides['download_link']
        ?? ($existing['download_link'] ?? null)
        ?? ($homepage ? rtrim($homepage, '/') . '/archive/refs/heads/main.zip' : '');

    $banners = $overrides['banners'] ?? ($existing['banners'] ?? []);

    $authorHtml = '';
    if ($authorProfile) {
        $authorHtml = "<a href='" . escapeHtml($authorProfile) . "'>" . escapeHtml((string) $authorName ?: (string) $authorProfile) . "</a>";
    } else {
        $authorHtml = escapeHtml((string) $authorName);
    }

    $info = [
        'name' => $name,
        'version' => $version,
        'author' => $authorHtml,
        'author_profile' => (string) $authorProfile,
        'homepage' => (string) $homepage,
        'download_link' => (string) $downloadLink,
        'support_url' => (string) $supportUrl,
        'requires' => (string) $requires,
        'tested' => (string) $tested,
        'requires_php' => (string) $requiresPhp,
        'last_updated' => $overrides['last_updated'] ?? formatUtcNow(),
        'sections' => [
            'description' => $overrides['description_html'] ?? normalizeSectionTextToHtml($sections['description'] ?? '', $md),
            'installation' => $overrides['installation_html'] ?? normalizeSectionTextToHtml($sections['installation'] ?? '', $md),
            'changelog' => $overrides['changelog_html'] ?? changelogTxtToHtml($sections['changelog'] ?? ''),
        ],
        'banners' => [
            'low' => $banners['low'] ?? ($homepage ? rtrim($homepage, '/') . '/raw/main/.wordpress-org/banner-772x250.jpg' : ''),
            'high' => $banners['high'] ?? ($homepage ? rtrim($homepage, '/') . '/raw/main/.wordpress-org/banner-1544x500.jpg' : ''),
        ],
    ];

    $json = json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    writeFileStr($outPath, $json);

    return $info;
}

function bumpVersionInMainPhp(string $mainPhpPath, string $newVersion): void
{
    $php = readFileStr($mainPhpPath);
    $updated = preg_replace('/(Version:\s*)([0-9]+(?:\.[0-9]+){0,3})/i', '$1' . $newVersion, $php, 1, $count);
    if (!$updated || $count === 0) {
        throw new RuntimeException("Could not find \"Version:\" in {$mainPhpPath}");
    }
    writeFileStr($mainPhpPath, $updated);
}

function bumpStableTagInReadme(string $readmePath, string $newVersion): void
{
    $txt = readFileStr($readmePath);
    $updated = preg_replace('/^(Stable tag:\s*)(.+)$/mi', '$1' . $newVersion, $txt, 1, $count);
    if (!$updated || $count === 0) {
        throw new RuntimeException("Could not find \"Stable tag:\" in {$readmePath}");
    }
    writeFileStr($readmePath, $updated);
}

function bumpReadmeMdVersion(?string $readmeMdPath, string $newVersion): void
{
    if (!$readmeMdPath)
        return;
    if (!file_exists($readmeMdPath))
        return;

    $md = readFileStr($readmeMdPath);
    // Best-effort: replace headings like "### v1.2.0"
    $updated = preg_replace('/(^###\s+v)([0-9]+(?:\.[0-9]+){0,3})/mi', '$1' . $newVersion, $md);
    if ($updated === null) {
        throw new RuntimeException("Regex error updating {$readmeMdPath}");
    }
    writeFileStr($readmeMdPath, $updated);
}

function ensureChangelogEntry(string $readmePath, string $version): void
{
    $txt = readFileStr($readmePath);

    $verRe = preg_quote($version, '/');
    if (preg_match('/^=\s*' . $verRe . '\s*=$/m', $txt)) {
        return;
    }

    if (!preg_match('/(==\s*Changelog\s*==\s*\R)/i', $txt)) {
        throw new RuntimeException("Could not find \"== Changelog ==\" in {$readmePath}");
    }

    $inserted = preg_replace(
        '/(==\s*Changelog\s*==\s*\R)/i',
        "$1\n= {$version} =\n* (TBD)\n\n",
        $txt,
        1
    );

    if ($inserted === null) {
        throw new RuntimeException("Regex error inserting changelog entry in {$readmePath}");
    }

    writeFileStr($readmePath, $inserted);
}

function usageAndExit(int $code): void
{
    fwrite(STDERR, "Usage:\n");
    fwrite(STDERR, "  php tools/wp-info.php generate --main=... --readme=... --out=...\n");
    fwrite(STDERR, "  php tools/wp-info.php bump --version=1.2.0 --main=... --readme=... --readmemd=... --out=...\n");
    fwrite(STDERR, "Options:\n");
    fwrite(STDERR, "  --ensure_changelog=1\n");
    exit($code);
}

try {
    $args = parseArgs($argv);
    $cmd = $args['_'][0] ?? null;

    $mainPhpPath = (string) ($args['main'] ?? './redirects.php');
    $readmePath = (string) ($args['readme'] ?? './readme.txt');
    $outPath = (string) ($args['out'] ?? './wp-info.json');
    $readmeMdPath = isset($args['readmemd']) ? (string) $args['readmemd'] : './README.md';

    if (!$cmd || ($cmd !== 'generate' && $cmd !== 'bump')) {
        usageAndExit(1);
    }

    if ($cmd === 'bump') {
        $newVersion = $args['version'] ?? null;
        if (!$newVersion || !is_string($newVersion) || trim($newVersion) === '') {
            fwrite(STDERR, "Missing --version=x.y.z\n");
            exit(1);
        }
        $newVersion = trim($newVersion);

        bumpVersionInMainPhp($mainPhpPath, $newVersion);
        bumpStableTagInReadme($readmePath, $newVersion);

        if (!empty($args['ensure_changelog'])) {
            ensureChangelogEntry($readmePath, $newVersion);
        }

        bumpReadmeMdVersion($readmeMdPath, $newVersion);
    }

    $overrides = [
        // Add overrides here if you want, or later wire them via CLI flags.
        // 'author_profile' => 'https://github.com/you',
        // 'homepage' => 'https://github.com/you/repo',
    ];

    $info = buildWpInfo([
        'mainPhpPath' => $mainPhpPath,
        'readmePath' => $readmePath,
        'outPath' => $outPath,
        'overrides' => $overrides,
    ]);

    fwrite(STDOUT, "Wrote {$outPath}\n");
    fwrite(STDOUT, "name={$info['name']} version={$info['version']} last_updated={$info['last_updated']}\n");
    exit(0);

} catch (Throwable $e) {
    fwrite(STDERR, ($e->getMessage() ?: 'Error') . "\n");
    $st = $e->getTraceAsString();
    if ($st)
        fwrite(STDERR, $st . "\n");
    exit(1);
}
