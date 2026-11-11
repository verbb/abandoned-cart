# Installation & Setup

You can install Abandoned Cart via the Plugin Store, or through Composer.

## Craft Plugin Store

To install **Abandoned Cart**, navigate to the _Plugin Store_ section of your Craft control panel, search for `Abandoned Cart`, and click the _Try_ button.

## Composer

You can also add the package to your project using Composer and the command line.

1. Open your terminal and go to your Craft project:

```shell
cd /path/to/project
```

2. Then tell Composer to require the plugin, and Craft to install it:

```shell
composer require verbb/abandoned-cart && php craft plugin/install abandoned-cart
```

## Licensing

You can try Abandoned Cart in a development environment for as long as you like. Once your site goes live, you are required to purchase a licence for the plugin through the Craft Plugin Store.

## After Installing

Installing Abandoned Cart creates a **Default** email template and a default sequence, **All Other Carts**, with three reminder emails. The sequence starts switched off, so no customer is emailed until you've reviewed it and turned it on.

Follow [Create Your First Sequence](create-your-first-sequence.md) to check your sending details, schedule the plugin's background run, send yourself a test and start recovering carts.
