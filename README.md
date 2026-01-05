# Redirects for WordPress

![WordPress Version](https://img.shields.io/badge/WordPress-6.8%2B-blue)
![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-blue)
![WP--CLI](https://img.shields.io/badge/WP--CLI-supported-green)
![License](https://img.shields.io/badge/License-GPL--2.0--or--later-green)

A **lightweight redirect manager** for WordPress with **zero bloat**.
Manage **301 / 302 redirects**, including **wildcards and capture groups**, with a simple admin UI and powerful **WP-CLI commands**.

- No SEO plugins.
- No custom tables.
- No rewrite rules.
- Just redirects.

---

## 📌 Features

* 🔁 **301 & 302 Redirects** – Explicit control over redirect type.
* 🧩 **Wildcard Support** – Use `*` in source paths with `$1`, `$2`… capture groups.
* 🔍 **Exact Match Priority** – Exact paths are matched before wildcards.
* 🔗 **Query String Preservation** – Automatically keeps `?utm=…` and other parameters.
* 🧑‍💻 **WP-CLI Support** – Manage redirects from the command line.
* 🧪 **Dry-Run Testing** – Test which rule matches without executing redirects.
* 🧠 **Single Option Storage** – Stored in one WordPress option (no DB tables).
* ⚡ **Fast & Early Execution** – Runs on `template_redirect` before theme rendering.
* 🎯 **Minimal Admin UI** – Manage redirects under **Tools → Redirects**.

Ideal for **site migrations, content restructuring, campaign URLs, and legacy cleanup**.

---

## 🚀 Installation

### **1. Install from ZIP**

1. Download the repository or latest release.
2. Upload the plugin to `/wp-content/plugins/redirects`.
3. Activate via **WordPress Admin → Plugins**.

### **2. Install via GitHub & Composer**

```sh
composer config repositories.wp-redirects vcs https://github.com/lightningspirit/wp-redirects.git
composer require lightningspirit/wp-redirects
```

### Activation

1. Activate the plugin in **WordPress Admin → Plugins**.
2. Go to **Tools → Redirects** to manage rules.
3. Optionally use **WP-CLI** for automation.

---

## 🧭 Usage

### Basic Redirect

```
/old-page  →  /new-page   (301)
```

### Wildcard Redirect

```
/old/*     →  /new/$1
```

**Example:**

* Request: `/old/blog/post-1?utm=abc`
* Redirects to: `/new/blog/post-1?utm=abc`

Query strings are preserved automatically.

---

## 🧑‍💻 WP-CLI Commands

### List redirects

```sh
wp redirect list
```

### Add or update a redirect

```sh
wp redirect add --from=/old/* --to=/new/$1 --type=301
```

### Delete a redirect

```sh
wp redirect delete --from=/old/*
```

### Export / Import

```sh
wp redirect export --file=redirects.json
wp redirect import --file=redirects.json
```

### Dry-run test (no redirect happens)

```sh
wp redirect test --url='/old/abc?utm=1'
```

Outputs:

```
Matched from: /old/*
Type: 301
Target: /new/abc?utm=1
```

---

## ❓ FAQ

### Does this replace Redirection or SEO plugins?

It replaces **only redirect management**. No analytics, no logs, no SEO features.

### Are regex rules supported?

No. Only `*` wildcards by design — safer, faster, and easier to reason about.

### Are redirects cached?

Redirects are loaded once per request from a single option. No runtime writes.

### Does it work on multisite?

Yes, per-site. Each site stores its own redirect rules.

### Does it support full URLs?

Yes. Targets can be relative (`/new`) or absolute (`https://example.com/new`).

---

## 📜 Changelog

### v0.1.2

* Added query string preservation
* Added `wp redirect test` dry-run command

### v0.1.1

* Added wildcard support with capture groups
* Added full WP-CLI management

### v0.1.0

* Initial release 🎉
* Admin UI for managing redirects
* Exact path matching with early execution

---

## 🛠 Development & Contribution

This plugin is intentionally **minimal**.
Contributions should preserve:

* Simplicity
* Performance
* No external dependencies

### Contribute:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature-name`)
3. Commit changes (`git commit -m "Add feature"`)
4. Push and open a **Pull Request**

---

## 🏆 Support

* 🐛 Issues & feature requests: GitHub Issues
* 🧑‍💻 WP-CLI questions: open an issue with command output

---

## 📜 License

Licensed under **GPL-2.0-or-later**.
See the `LICENSE` file for details.