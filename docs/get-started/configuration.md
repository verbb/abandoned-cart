# Configuration

You can customise Abandoned Cart’s settings using a PHP configuration file. This is optional: each setting has a default, so you only need to include the values you want to change.

To override a setting, create `abandoned-cart.php` in your Craft project’s `/config` directory and return an array of setting names and values. For example, the following will treat a cart as abandoned after two hours without changes:

```php
<?php

return [
    'abandonedAfterMinutes' => 120,
];
```

All other settings keep their defaults. Add any further settings you want to change to the same array. The options below explain the available settings and their defaults. You can also manage many of these settings in the control panel under **Abandoned Carts → Settings**.

## Configuration Options

### General

::: reference
#### `pluginName`

**Type:** `string` · **Default:** `'Abandoned Carts'`

The name displayed for the plugin in the control panel navigation, page titles and the tab on user accounts.
:::

### Recovery Defaults

Every sequence uses these settings unless it overrides them. [Sequences](../feature-tour/sequences.md#overriding-the-recovery-defaults) explains how to give one sequence different timing or a different return page.

::: reference
#### `abandonedAfterMinutes`

**Type:** `int` · **Default:** `60`

How long a cart goes without changes before it counts as abandoned. The time is measured from the last change to the Commerce order, such as adding an item or entering an address. The first email in a sequence is timed from this point, so with the default of 60, an email set to send **Straight away** goes out about an hour after the customer last touched their cart.

A shorter value catches customers while they're still thinking about their purchase but risks emailing people who are only browsing between pages. The minimum is `1`.
:::

::: reference
#### `returnUrl`

**Type:** `string|null` · **Default:** `'shop/cart'`

Where a recovery link takes the customer once their cart has been restored, usually your cart or checkout page. A relative path is resolved against the site the cart belongs to. You can also use a full URL or an environment variable reference.
:::

::: reference
#### `recoveryWindowMinutes`

**Type:** `int` · **Default:** `10080` (7 days)

The recovery period, meaning how long a cart stays in recovery after it's abandoned. It's labelled **Recovery Ends After** in the control panel. When this time is up, any emails that haven't been sent are cancelled and the cart's recovery links stop restoring it. Make sure it's long enough for every email in your longest sequence to be sent.
:::

::: reference
#### `attributionWindowMinutes`

**Type:** `int` · **Default:** `7200` (5 days)

How long after the cart's last email a completed order by the same customer counts as recovered, whether or not the customer clicked the email's link, and whether they completed this cart or another one. An order completed after the window has closed doesn't count, even if the customer followed a link. [Overview](../feature-tour/overview.md#how-recoveries-are-credited) explains how recoveries are credited.
:::

::: reference
#### `contactLimitMinutes`

**Type:** `int` · **Default:** `10080` (7 days)

How long a customer must go without a recovery email before another of their carts can start a new sequence, once their last recovery has ended. Customers who abandon often would otherwise receive a stream of reminders. A cart held back by this limit is recorded as skipped, with the reason **Contacted recently**. A newer cart that takes over a recovery still under way isn't held back, because it carries on the same schedule. Only emails that were actually sent count. Set it to `0` to turn the limit off.
:::

### Who Can Be Emailed

::: reference
#### `consentFieldHandle`

**Type:** `string|null` · **Default:** `null`

The handle of a Lightswitch field that records whether a customer agreed to be emailed. When it's set, only customers who switched the field on are emailed, and everyone else is recorded as skipped with the reason **Didn't opt in**. The field is read from the order first, then from the customer's user account. If neither has the field, the customer counts as not having agreed.

If the field is later deleted, or its handle changes, no order or account has a field with this handle, so nobody is emailed and every cart is skipped as **Didn't opt in**. System Status warns when the field can't be found.

Leave this empty if every eligible cart can be contacted where you trade. [Customers](../feature-tour/customers.md#consent) explains how consent interacts with unsubscribing.
:::

::: reference
#### `returningCustomersOnly`

**Type:** `bool` · **Default:** `false`

Whether to email only customers who have completed an order before, matched by email address. Carts from first-time customers are recorded as skipped, with the reason **Not a returning customer**.

To treat returning customers differently without excluding everyone else, use the **Returning Customers** audience on a sequence instead.
:::

::: reference
#### `blockedDomains`

**Type:** `string[]` · **Default:** `[]`

Email domains that are never emailed, such as your own company's domain or a domain used for test orders. A leading `@` is ignored, and domains are compared without regard to case. Each domain must match the part of the address after `@` exactly, so `example.com` doesn't block `shop.example.com`.

In `config/abandoned-cart.php`, add the domains to the returned array:

```php
'blockedDomains' => ['example.com', 'test.example.com'],
```

In the control panel, enter one domain per line. To stop emailing one person, add them to [Do Not Email](../feature-tour/customers.md#do-not-email) instead.
:::

### Customer Pages

::: reference
#### `customerPageTemplate`

**Type:** `string|null` · **Default:** `null`

A site template that Abandoned Cart's customer pages render inside, so they look like the rest of your store. These are the pages a customer sees when a recovery link has expired, is invalid, or leads to an order that's already complete or a cart that's now empty, along with the unsubscribe pages. The template must define a `content` block. Leave it empty to use the standalone pages. If the template can't be found, the standalone pages are used instead. [Styling Customer Pages](../template-guides/styling-customer-pages.md) walks through an example.
:::

### Sending

::: reference
#### `deliveryPaused`

**Type:** `bool|string` · **Default:** `false`

Holds every recovery email across all sequences. Carts still enter sequences while sending is paused, and their emails wait. Once sending resumes, any that are due go out on the next run, unless the cart's recovery period has ended in the meantime. Use it for emergencies, such as a problem with an email's wording, or to stop a development environment from emailing customers. The control panel switch is read-only where admin changes are off, so on a live site this setting is how you pause. It accepts an environment variable reference such as `'$ABANDONED_CART_PAUSED'`, and reads `true`, `1`, `on` or `yes` as paused.

Pause sending on any development or staging site that holds a copy of production's database. The background run also starts from ordinary site and control panel requests, so browsing that site would otherwise email real customers, even without cron. [Pausing All Sending](../feature-tour/sending-scheduling.md#pausing-all-sending) explains more.
:::

::: reference
#### `historyRetentionDays`

**Type:** `int` · **Default:** `365`

How many days to keep finished and skipped carts in Abandoned Cart's history. Older records are removed during Craft's garbage collection, which also removes them from the Overview's totals. Carts still in recovery are never removed. Customers and their unsubscribe or Do Not Email status are kept until someone [deletes the customer](../feature-tour/customers.md#deleting-a-customer). The minimum is `30`.
:::

### Configuration File Only

These settings don't appear in the control panel.

::: reference
#### `maxEnrollmentsPerRun`

**Type:** `int` · **Default:** `100`

The most carts that one background run starts sequences for, or records as skipped. Any carts beyond the limit are picked up by the following runs. Lower it if runs take too long on a busy store with a slow mail server, or raise it, up to `1000`, if carts are abandoned faster than runs can keep up. It doesn't limit emails. Each run sends at most 100 due emails, which can't be changed.
:::

::: reference
#### `compatibilityMode`

**Type:** `bool` · **Default:** `true`

This setting is only for sites upgraded from Abandoned Cart 4, and the [upgrade guide](upgrading-from-v4.md#compatibility-mode) explains it.
:::

::: reference
#### `passKey`

**Type:** `string|null` · **Default:** `null`

This setting is only for sites upgraded from Abandoned Cart 4 that still call its scheduler URL, and the [upgrade guide](upgrading-from-v4.md#http-trigger) explains it.
:::
