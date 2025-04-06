# Rector Auto Upgrade Composer Plugin

A Composer plugin that automatically runs Rector upgrades for your installed packages after a `composer update`.

## Description

This plugin scans your installed packages after running `composer update` and checks if they provide Rector upgrade configurations. If found, it offers to automatically apply these upgrades to your codebase, making package version migrations easier.

## Installation

Install the package via Composer:

```bash
composer require --dev atournayre/rector-auto-upgrade
```

## Requirements

- PHP 8.0 or higher
- Composer 2.0 or higher
- Rector must be installed in your project

## How It Works

1. After running `composer update`, the plugin activates
2. It scans all installed packages for Rector upgrade configurations at `vendor/package-name/rector/upgrade/[X.Y.Z]/rector.php`
3. For each found configuration, it prompts you if you want to run the upgrade
4. If you confirm, it creates a temporary configuration file and runs Rector with that configuration

## Package Compatibility

For package maintainers who want to make their packages compatible with this plugin, you need to:

1. Create a directory structure in your package: `rector/upgrade/[X.Y.Z]`
2. Add a `rector.php` configuration file in that directory
3. The plugin will automatically detect this configuration during updates

Example directory structure in your package:
```
├── src/
├── rector/
│   └── upgrade/
│       └── 2.0.0/
│           └── rector.php
└── composer.json
```

## Configuration Variables

In your Rector configuration file, you can use the following variable:

- `%currentWorkingDirectory%` - Will be replaced with the absolute path to the user's project root

Example of a Rector configuration file:

```php
<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        '%currentWorkingDirectory%/src',
    ]);
};
```

## Development Environment Detection

The plugin only runs in development environments. An environment is considered a development environment if:

- The Rector binary is installed
- The `APP_ENV` environment variable is set to `dev`
- The `APP_ENV` environment variable is not set to `prod` or `production`
