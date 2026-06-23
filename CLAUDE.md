# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

`flagbit/shopware-maintenance` is a Symfony bundle (installed into a Shopware 6 project) that provides two CLI commands to declaratively manage plugin states and system config values across environments. It has no frontend, no database migrations, and no event subscribers — only two console commands and their DI wiring.

## Tech Stack

- PHP (Symfony Bundle, PSR-4), compatible with Shopware 6.5/6.6 and Symfony 6.3/7.0
- No build tools, no JS, no test suite in this repo

## Architecture

The bundle is intentionally minimal:

```
ShopwareMaintenance.php          # Bundle entry point (empty, relies on Extension auto-discovery)
DependencyInjection/
  ShopwareMaintenanceExtension.php  # Loads Resources/config/services.xml
Resources/config/services.xml    # Wires both commands into the Symfony container
Command/
  PluginSynchronizeCommand.php   # bin/console plugin:sync
  ConfigSynchronizeCommand.php   # bin/console config:sync
```

**`plugin:sync`** reads `config/plugins.php` from the host project's root (`%kernel.project_dir%`). It processes four ordered groups (`core` → `third_party` → `agency` → `project`) to respect plugin dependency order. For each plugin it delegates to native `plugin:uninstall` / `plugin:install --activate` commands. **Always run `bin/console plugin:refresh` before `plugin:sync`** — without it, newly added plugin classes won't be discovered and `plugin:sync` will fail with `PluginBaseClassNotFoundException`.

**`config:sync`** reads `config/config.yaml` from the host project root (path overridable via optional argument). The `global` key maps to null salesChannelId (all channels); other top-level keys are matched against SalesChannel translation names (not UUIDs, which vary per environment). Values are compared as strings before writing to avoid unnecessary writes.

## Installation (into a Shopware project)

```bash
composer require flagbit/shopware-maintenance
```

Register in `config/bundles.php`:
```php
Flagbit\Shopware\ShopwareMaintenance\ShopwareMaintenance::class => ['all' => true],
```

## Commands

```bash
bin/console plugin:refresh          # must run before plugin:sync
bin/console plugin:sync             # sync plugin states from config/plugins.php
bin/console config:sync             # sync system config from config/config.yaml
bin/console config:sync path/to/custom.yaml  # optional custom config path
```

## Conventions

- `config/plugins.php` must return an array with keys `core`, `third_party`, `agency`, `project`; values are `pluginName => bool`
- `config/config.yaml` uses `global` as the reserved key for store-wide config; all other keys match SalesChannel translation names
- SalesChannel names are used instead of UUIDs intentionally — UUIDs differ between environments
