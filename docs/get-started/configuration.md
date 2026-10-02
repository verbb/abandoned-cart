# Configuration

You can customise Abandoned Cart’s settings using a PHP configuration file. This is optional: each setting has a default, so you only need to include the values you want to change.

To override a setting, create `abandoned-cart.php` in your Craft project’s `/config` directory and return an array of setting names and values. For example, the following will change the name displayed in the control panel:

```php
<?php

return [
    'pluginName' => 'Abandoned Cart Tools',
];
```

All other settings keep their defaults. Add any further settings you want to change to the same array. The options below explain the available settings and their defaults.

## Configuration Options

::: reference
### `pluginName`

**Type:** `string` · **Default:** `'Abandoned Carts'`

The name displayed for the plugin in the control panel.
:::

::: reference
### `passKey`

**Type:** `string|null` · **Default:** `null`

A generated bearer credential for the optional HTTP scheduler endpoint. Send it only in the `Authorization` header; the console scheduler does not use it. A unique key is generated when this value is empty.
:::

::: reference
### `restoreExpiryHours`

**Type:** `int|string|null` · **Default:** `48`

How many hours should abandoned cart restore links last for after being sent.
:::

::: reference
### `firstReminderDelay`

**Type:** `int|string|null` · **Default:** `1`

How many hours after a cart has been abandoned should the 1st reminder be sent.
:::

::: reference
### `secondReminderDelay`

**Type:** `int|string|null` · **Default:** `12`

How many hours after a cart has been abandoned should the 2nd reminder be sent.
:::

::: reference
### `recipientCooldownHours`

**Type:** `int|string|null` · **Default:** `24`

How many hours a recipient must wait before another reminder sequence can be created. Recipient matching ignores surrounding whitespace and letter case, and an unfinished sequence always remains exclusive.
:::

::: reference
### `maxEnrollmentsPerRun`

**Type:** `int|string|null` · **Default:** `100`

The maximum number of new reminder sequences that can be created each time abandoned carts are processed.
:::

::: reference
### `staleRecordRetentionDays`

**Type:** `int|string|null` · **Default:** `90`

How many days inactive abandoned cart records should be retained. Cleanup never shortens configured reminder delays or restore-link expiry.
:::

::: reference
### `discountCode`

**Type:** `string|null` · **Default:** `null`

Enter the discount code that abandoned carts can use.
:::

::: reference
### `firstReminderTemplate`

**Type:** `string|null` · **Default:** `'abandoned-cart/emails/first'`

Use a custom template for the 1st reminder email.
:::

::: reference
### `secondReminderTemplate`

**Type:** `string|null` · **Default:** `'abandoned-cart/emails/second'`

Use a custom template for the 2nd reminder email.
:::

::: reference
### `firstReminderSubject`

**Type:** `string|null` · **Default:** `'You‘ve left some items in your cart'`

The subject for the 1st reminder email.
:::

::: reference
### `secondReminderSubject`

**Type:** `string|null` · **Default:** `'Your items are still waiting - don‘t miss out'`

The subject for the 2nd reminder email.
:::

::: reference
### `recoveryUrl`

**Type:** `string|null` · **Default:** `'shop/cart'`

By default recovered carts will be redirected to shop/cart, use this field if you use something different.
:::

::: reference
### `disableSecondReminder`

**Type:** `bool|string|null` · **Default:** `false`

Whether to disable the second reminder email. Set this to `true` to send only the first reminder.
:::

::: reference
### `blacklist`

**Type:** `string|null` · **Default:** `null`

Enter emails or domains separated by a comma that should be ignored.
:::

::: reference
### `includeBlacklisted`

**Type:** `bool` · **Default:** `true`

Whether blacklisted carts should be included in the Dashboard.
:::



## Control Panel
You can also manage configuration settings through the Control Panel by visiting Settings → Abandoned Cart.
