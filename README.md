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

### Block admin plugin refresh during deployment

The check automatically uses `<deploy_path>/.dep/deploy.lock` when the resolved
application root is `<deploy_path>/releases/<release>` and `.dep` exists.
Symlinks such as `current` are resolved first. No environment setting is needed.
Local checkouts, paths that cannot be resolved and other directory layouts are
not protected. PHP-FPM must be able to access the deployment directories.

While the file exists, `POST /api/_action/extension/refresh` returns HTTP 503
without running the refresh. This prevents the old release's admin refresh
from removing plugin records added by the new release during deployment.
No changes to the Deployer recipe are required.

Install this protection in the serving release before relying
on it for a later deployment. It only guards this HTTP route, not CLI commands
or indirect refresh calls from other plugin operations. It does not wait for
requests already running. When Deployer removes the lock, including after a
failed deployment, refresh is allowed again even if the old release is still
serving. The check does not create, remove or hold the lock.

The handler uses APIs available in Shopware 6.5, 6.6 and 6.7. The package's
existing dependency requirements still apply; they are not widened by this feature.

### Install/Uninstall plugins

The file `config/plugins.php` defines which Shopware plugins should be enabled or disabled.
It is divided in different groups `core`, `third_party`, `agency` and `project`, which are installed in exactly this order.


**Example**

```php
# config/plugins.php

<?php declare(strict_types=1);

# be awere those are sorted lists because of plugin dependencies may occur
return [
    'core' => [                                     # original plugins from shopware
        'SwagMarkets' => false,                     # disabled
        'SwagPayPal' => true,                       # enabled
        'SwagPlatformDemoData' => false,            # disabled
    ],
    'third_party' => [                              # plugins from other vendors
        'CoeWishlistSw6' => ture,                   # enabled
        'DreiscSeoPro' => false,                    # disabled
        'GoogleConsentV2' => true,                  # enabled
        'IesCommissionField6' => true,              # enabled
    ],
    'agency' => [                                   # plugins from flagbit for company-wide usage
        'FlagbitGoogleConsentMode' => true,         # enabled
    ],
    'project' => [                                  # plugins for this project
        'CustomerStoreApi' => false,                # disabled
        'CustomerWishlistCommissionField' => true,  # enabled
    ],
];

```

#### Groups

##### Core
In the group `core` should be the plugins from shopware itself. Those mostly start with `Swag`.

##### Third-Party
The group `third_party` is for plugin of other agencies which we get from the store.

##### Agency
Plugins which are from flagbit but aren't for specific for this project and can be used in other projects as well, it should be in `agency`.

##### Project
The group `project` is for plugins which are specifically developed for this project.

### Define Config

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
    "core.listing.allowBuyInListing": false
    "core.listing.showReview": false
"Headless":
    "core.listing.productsPerPage": 48

```

### Commands

```bash
bin/console config:sync # synchronizes configuration values as defined in config/config.yaml
bin/console plugin:refresh # ensure plugins classes are loaded before plugin:sync execution
bin/console plugin:sync # synchronizes plugin enable/disable status as defined in config/plugins.php  
```

## Troubleshooting

<br>

<p align="center">
Supported with :heart: by <a href="https://www.flagbit.de">Flagbit GmbH & Co. KG</a>
</p>
