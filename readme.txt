=== Redirects ===
Contributors:      lightningspirit
Tags:              redirects, 301, 302, wp-cli, wildcard, migration
Requires at least: 6.8
Tested up to:      6.9
Requires PHP:      7.4
Stable tag:        0.1.2
License:           GPL-2.0-or-later
License URI:       [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html)

A lightweight redirect manager for WordPress with zero bloat. Manage 301 / 302 redirects with wildcard support and WP-CLI.

== Description ==

**Redirects** is a lightweight redirect manager for WordPress designed to do one thing well: handle redirects.

It allows you to manage **301 and 302 redirects**, including **wildcards and capture groups**, through a minimal admin interface or via **WP-CLI**.

There are:

* No SEO plugins
* No custom database tables
* No rewrite rules

Just redirects.

* **301 & 302 Redirects** – Explicit control over redirect type
* **Wildcard Support** – Use `*` in source paths with `$1`, `$2`, etc. capture groups
* **Exact Match Priority** – Exact paths are matched before wildcards
* **Query String Preservation** – Keeps `?utm=` and other parameters automatically
* **WP-CLI Support** – Manage redirects from the command line
* **Dry-Run Testing** – Test which rule matches without executing redirects
* **Single Option Storage** – Stored in one WordPress option (no custom tables)
* **Fast Execution** – Runs early on `template_redirect`
* **Minimal Admin UI** – Manage redirects under **Tools → Redirects**

Ideal for **site migrations, URL restructuring, campaign URLs, and legacy cleanup**.

== Installation ==

1. Download the plugin or clone the repository.
2. Upload the plugin to the `/wp-content/plugins/redirects` directory.
3. Activate the plugin via **WordPress Admin → Plugins**.
4. Go to **Tools → Redirects** to manage redirect rules.

Optionally, you can manage redirects using **WP-CLI**.

== Usage ==

= Basic Redirect =

```
/old-page  →  /new-page   (301)
```

= Wildcard Redirect =

```
/old/*     →  /new/$1
```

Example:

* Request: `/old/blog/post-1?utm=abc`
* Redirects to: `/new/blog/post-1?utm=abc`

Query strings are preserved automatically.

= WP-CLI Commands =

List redirects:

```
wp redirect list
```

Add or update a redirect:

```
wp redirect add --from=/old/* --to=/new/$1 --type=301
```

Delete a redirect:

```
wp redirect delete --from=/old/*
```

Export / Import redirects:

```
wp redirect export --file=redirects.json
wp redirect import --file=redirects.json
```

Dry-run test:

```
wp redirect test --url='/old/abc?utm=1'
```

== Frequently Asked Questions ==

= Does this replace Redirection or SEO plugins? =

It replaces **only redirect management**. It does not provide analytics, logs, or SEO features.

= Are regex rules supported? =

No. Only `*` wildcards are supported by design for simplicity and safety.

= Are redirects cached? =

Redirects are loaded once per request from a single WordPress option.

= Does it work on multisite? =

Yes. Redirects are stored per site.

= Does it support full URLs? =

Yes. Targets can be relative (`/new`) or absolute (`https://example.com/new`).

= Does this plugin conflict with others? =

This plugin conflicts with **Redirection** and potentially other plugins that solve the redirect issue.
Only one redirect manager should be active at a time.

== Changelog ==

= 0.1.2 =

* Added query string preservation
* Added `wp redirect test` dry-run command

= 0.1.1 =

* Added wildcard support with capture groups
* Added full WP-CLI management

= 0.1.0 =

* Initial release
* Admin UI for managing redirects
* Exact path matching with early execution

== Screenshots ==

1. Redirects admin screen under Tools → Redirects
2. WP-CLI usage examples

== Support ==

* Bug reports and feature requests: GitHub Issues
* WP-CLI questions: please include command output

== License ==

This plugin is licensed under the **GPL-2.0-or-later** license. See the LICENSE file for details.
