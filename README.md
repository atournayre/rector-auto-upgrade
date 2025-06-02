# Rector Auto Upgrade Composer Plugin

**Automate code migrations related to dependency version upgrades using Rector.**  
This Composer plugin enables package maintainers to provide versioned Rector sets that are automatically executed when packages are updated.

---

## ✨ Purpose

`rector-auto-upgrade` defines a standard for embedding versioned Rector "sets" into PHP packages, allowing automated code migration when users upgrade dependencies.

Each version of a package can provide a Rector set describing necessary changes (e.g., class renaming, method signature updates, deprecations, etc.).

---

## 📦 How It Works

- When `composer update` is executed,
- The plugin detects which packages are being updated,
- For each updated package, it looks for a Rector set matching the new version,
- If found, the Rector set is executed to automatically refactor the user's code.

---

## 🗂️ Expected Package Structure

Each package providing migration support must include the following structure:

```
my-package/
├── rector/
│   └── sets/
│       ├── 2.0.0.php
│       ├── 2.1.0.php
```

- Each file name corresponds to a target version of the package.
- The content of each file must return a standard Rector configuration closure.

---

## ⚙️ Installation

```bash
composer require --dev atournayre/rector-auto-upgrade
```

⚠️ This plugin must only be used in a **development environment**.

---

## 📋 Requirements

- PHP >= 8.1
- [Rector](https://github.com/rectorphp/rector) must be installed in the project.
- Composer version 2.0 or higher.
- Packages must include `rector/sets/{version}.php` files for their upgrades.

---

## 🧪 Example Usage

1. A package `my/package` provides a file `rector/sets/2.0.0.php`.
2. The user updates `my/package` from version `1.4.0` to `2.0.0`.
3. The plugin detects this version change, finds the matching Rector set, and runs it.
4. The user’s code is automatically updated to comply with `my/package` version `2.0.0`.

---

## ❗ Limitations & Recommendations

- This plugin is intended **only for local**.
- Use version control to review and commit changes after execution.
- Only upgrades to higher versions are currently supported.

---

## 🤝 Contributing

Feedback and contributions are welcome.  
Feel free to open an issue or submit a pull request.
