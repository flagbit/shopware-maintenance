<h1 align="center">
  Flagbit Shopware Maintenance
  <br>
</h1>

<h4 align="center">A Shopware extension for easy maintenance</h4>

<p align="center">
    <a href="https://github.com/flagbit/shopware-maintenance/actions"><img src="https://github.com/flagbit/waldlaeufer-shopware-shop/workflows/Branch%20Name%20Checker/badge.svg"></a>
    <a href="https://github.com/flagbit/shopware-maintenance/actions"><img src="https://github.com/flagbit/waldlaeufer-shopware-shop/workflows/Tests/badge.svg"></a>
</p>

<p align="center">
  <a href="#installation">Installation</a> •
  <a href="#development">Development</a> •
  <a href="#troubleshooting">Troubleshooting</a>
</p>

## Installation

```bash
# adds flagbit packeton as composer repository since there is where package (private) is found
composer config repositories.flagbit-packeton composer https://packeton.flagbit.cloud/
# install package
composer require flagbit/shopware-maintenance
```

## Usage

### Configuration

The file `config/plugins.php` defines which Shopware plugins should be enabled or disabled.

**Example**

```php
# config/plugins.php

<?php declare(strict_types=1);

# be a were this is a sorted list because of plugin dependencies may occur
return [
    'FlagbitBaseTheme' => true,      # enabled
    'SwagMarkets' => false,          # disabled
    'SwagPayPal' => true,            # enabled
    'SwagPlatformDemoData' => false, # disabled
    'WebkulAkeneo' => true,          # enabled
];

```

The file `config/config.yaml` defines Shopware plugin configuration values to be set.

**Example**

```yaml
# config/config.yaml

global:
    "core.listing.productsPerPage": 48
Storefront:
    "core.listing.productsPerPage": 48
    "core.listing.allowBuyInListing": false
    "core.listing.showReview": false
Headless:
    "core.listing.productsPerPage": 48

```

### Commands

```bash
bin/console config:sync # synchronizes configuration values as defined in config/config.yaml
bin/console plugin:sync # synchronizes plugin enable/disable status as defined in config/plugins.php 
```

## Troubleshooting

<br>

<p align="center">
Supported with :heart: by <a href="https://www.flagbit.de">Flagbit GmbH & Co. KG</a>
</p>
