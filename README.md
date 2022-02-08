<h1 align="center">
  Flagbit Shopware Maintenance
  <br>
</h1>

<h4 align="center">A Shopware extension for easy maintenance</h4>

<p align="center">
  <a href="#installation">Installation</a> •
  <a href="#development">Development</a> •
  <a href="#troubleshooting">Troubleshooting</a>
</p>

## Installation

### Step 1: Download the bundle

Open a command console, enter your project directory and execute the
following command to download the latest stable version of this bundle:

```console
$ composer require flagbit/shopware-maintenance
```

### Step 2: Enable the Bundle

Then, enable the bundle by adding it to the list of registered bundles
in the `config/bundles.php` file of your project:

```php
// config/bundles.php

return [
    // ...
    Flagbit\Shopware\ShopwareMaintenance\ShopwareMaintenance::class => ['all' => true],
];
```

## Usage

The file `config/plugins.php` defines which Shopware plugins should be enabled or disabled.

**Example**

```php
# config/plugins.php

<?php declare(strict_types=1);

# be a were this is a sorted list because of plugin dependencies may occur
return [
    'SwagMarkets' => false,          # disabled
    'SwagPayPal' => true,            # enabled
    'SwagPlatformDemoData' => false, # disabled
];

```

The file `config/config.yaml` defines Shopware plugin configuration values to be set.

**Example**  
Use **global** for all SalesChannels. Or use the **name** from one SalesChannel translation to update the config.  
We do not use the **uuid** from the SalesChannel because this can be different from envoirment to envoirment.
```yaml
# config/config.yaml

"global":
    "core.listing.productsPerPage": 48
"Store Name":
    "core.listing.productsPerPage": 48
    "core .listing.allowBuyInListing": false
    "core.listing.showReview": false
"Headless":
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
