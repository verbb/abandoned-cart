# Configuration

You can customise Abandoned Cart's settings using a PHP configuration file. This is optional: each setting has a default, so you only need to include the values you want to change.

To override a setting, create `abandoned-cart.php` in your Craft project's `/config` directory and return an array of setting names and values. For example, the following treats a cart as abandoned after two hours without changes, instead of one:

```php
<?php

return [
    'abandonedAfterMinutes' => 120,
];
```

All other settings keep their defaults. Add any further settings you want to change to the same array. Durations are always given in minutes, so a day is `1440` and a week is `10080`.

Values in this file take precedence over those saved in the control panel. The matching field under **Settings → General Settings** shows a warning to explain that the file is in control.

## Environment-Specific Settings

Craft reads environment keys in a configuration file, so you can change a setting for one environment only. A common example is pausing every recovery email on development and staging sites, where a copy of the production database contains real customers' addresses:

```php
<?php

return [
    '*' => [
        'abandonedAfterMinutes' => 60,
    ],
    'dev' => [
        'deliveryPaused' => true,
    ],
    'staging' => [
        'deliveryPaused' => true,
    ],
];
```

`deliveryPaused` and `returnUrl` also accept an environment variable reference such as `'$ABANDONED_CART_PAUSED'`, if you'd rather control them from your `.env` file.

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

How long a cart goes without changes before it counts as abandoned. The time is measured from the last change to the Commerce order, such as adding an item or entering an address. The first email in a sequence is timed from this point, so the default of 60 sends an email set to go out "straight away" about an hour after the customer last touched their cart.

A shorter value catches customers while they're still thinking about their purchase but risks emailing people who are only browsing between pages. The minimum is `1`.
:::

::: reference
#### `returnUrl`

**Type:** `string|null` · **Default:** `'shop/cart'`

Where a recovery link takes the customer once their cart has been restored, usually your cart or checkout page. A relative path is resolved against the site the cart belongs to; you can also use a full URL or an environment variable reference.
:::

::: reference
#### `recoveryWindowMinutes`

**Type:** `int` · **Default:** `10080` (7 days)

How long a cart stays in recovery after it's abandoned. When this time is up, any emails that haven't been sent are cancelled and the cart's recovery links stop restoring it. Make sure the window is long enough for every email in your longest sequence to be sent.
:::

::: reference
#### `attributionWindowMinutes`

**Type:** `int` · **Default:** `7200` (5 days)

How long after an email a completed order still counts as recovered, even if the customer didn't click the email's link. The window is measured from the last email the cart was sent. [Overview](../feature-tour/overview.md#how-recoveries-are-credited) explains how recoveries are credited.
:::

::: reference
#### `contactLimitMinutes`

**Type:** `int` · **Default:** `10080` (7 days)

How long a customer must go without a recovery email before another of their carts can start a sequence. Customers who abandon often would otherwise receive a stream of reminders. A cart held back by this limit is recorded as skipped, with the reason **Contacted recently**. Only emails that were actually sent count. Set it to `0` to turn the limit off.
:::

### Who Can Be Emailed

::: reference
#### `consentFieldHandle`

**Type:** `string|null` · **Default:** `null`

The handle of a Lightswitch field that records whether a customer agreed to be emailed. When it's set, only customers who switched the field on are emailed; everyone else is recorded as skipped with the reason **Didn't opt in**. The field is read from the order first, then from the customer's user account. If neither has the field, the customer counts as not having agreed.

Leave this empty if every eligible cart can be contacted where you trade. [Customers](../feature-tour/customers.md#consent) explains how consent interacts with unsubscribing.
:::

::: reference
#### `returningCustomersOnly`

**Type:** `bool` · **Default:** `false`

Whether to email only customers who have completed an order before, matched by email address. Carts from first-time customers are recorded as skipped, with the reason **Not a returning customer**.

To treat returning customers differently without excluding everyone else, use the Returning Customers audience on a sequence instead.
:::

::: reference
#### `blockedDomains`

**Type:** `string[]` · **Default:** `[]`

Email domains that are never emailed, such as your own company's domain or a domain used for test orders. A leading `@` is ignored, and domains are compared without regard to case. Each domain must match the part of the address after `@` exactly, so `example.com` doesn't block `shop.example.com`.

```php
'blockedDomains' => ['example.com', 'test.example.com'],
```

In the control panel, enter one domain per line. To stop emailing one person, add them to [Do Not Email](../feature-tour/customers.md#do-not-email) instead.
:::

### Customer Pages

::: reference
#### `customerPageTemplate`

**Type:** `string|null` · **Default:** `null`

A site template that the expired-link, invalid-link and unsubscribe pages render inside, so they look like the rest of your store. The template must define a `content` block. Leave it empty to use Abandoned Cart's standalone pages. [Styling Customer Pages](../template-guides/styling-customer-pages.md) walks through an example.
:::

### Sending

::: reference
#### `deliveryPaused`

**Type:** `bool|string` · **Default:** `false`

Holds every recovery email across all sequences. Carts still enter sequences while sending is paused, and their emails wait; once sending resumes, any that are due go out on the next run, unless the cart's recovery period has ended in the meantime. Use it for emergencies, such as a problem with an email's wording, or to stop a development environment from emailing customers.
:::

::: reference
#### `historyRetentionDays`

**Type:** `int` · **Default:** `365`

How many days to keep finished and skipped carts in Abandoned Cart's history. Older records are removed during Craft's garbage collection, which also removes them from the Overview's totals. Carts still in recovery are never removed, and neither are customers or their Do Not Email status. The minimum is `30`.
:::

### Configuration File Only

These settings don't appear in the control panel.

::: reference
#### `maxEnrollmentsPerRun`

**Type:** `int` · **Default:** `100`

The most carts that one background run starts sequences for, or records as skipped. Any carts beyond the limit are picked up by the following runs. Lower it if runs take too long on a busy store with a slow mail server; raise it, up to `1000`, if carts are abandoned faster than runs can keep up.
:::

::: reference
#### `compatibilityMode`

**Type:** `bool` · **Default:** `true`

Keeps developer-facing features from Abandoned Cart 4 working while you update your templates and code: the 4.x email template variables, the `beforeMailSend` event and the old console command. Switch it off once your templates and code no longer use them. [Upgrading from v4](upgrading-from-v4.md#compatibility-mode) lists what it covers.
:::

## Control Panel

Administrators can manage most settings by visiting **Abandoned Carts → Settings → General Settings**, or **Settings → Plugins → Abandoned Cart**. These settings are stored in Craft's project config, so they follow the same rules as other project config: change them where admin changes are allowed, such as your development environment, and deploy them to other environments.

The **Settings** section also holds **Email Templates**, described in [Emails and Email Templates](../feature-tour/emails-and-email-templates.md#email-templates), and **System Status**, described in [Sending and Scheduling](../feature-tour/sending-and-scheduling.md#system-status).
