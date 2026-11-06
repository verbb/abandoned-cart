# Upgrading from v4

Abandoned Cart 5 replaces the first and second reminders with sequences of up to five emails, and adds email templates, customer records, a Carts screen and reporting. This guide covers upgrading from Abandoned Cart 4 on Craft 5 and Commerce 5. A site still on Abandoned Cart 3 and Craft 4 can upgrade straight to Abandoned Cart 5 as part of moving to Craft 5; the same conversion runs.

The upgrade's migration converts your settings, reminders and history automatically, and [What the Upgrade Converts](#what-the-upgrade-converts) describes the result. Some things can't be changed for you, such as your configuration file, cron jobs and custom code, and those are listed first.

## Breaking Changes

Review each of these that applies to your site:

- **Everyone:** your two reminders become the Everyone Else sequence, and their timing is converted. Check the [reminder timing](#reminder-timing) and the email wording after upgrading.
- **Sites with an `abandoned-cart.php` configuration file:** the 4.x settings are converted, but no longer read afterwards. Update the file using the [settings mapping](#settings).
- **Sites that run reminders from cron:** the console command has a new name. The old one keeps working while [compatibility mode](#compatibility-mode) is on. See [Console Command](#console-command).
- **Sites that trigger reminders over HTTP:** the endpoint keeps working while [compatibility mode](#compatibility-mode) is on, with the same pass key. See [HTTP Trigger](#http-trigger).
- **Sites with custom reminder templates:** template variables have changed, and the 4.x bundled templates are removed. See [Email Templates](#email-templates).
- **Modules and plugins that listen to Abandoned Cart events:** the cart save events are removed, and `beforeMailSend` only runs in compatibility mode. See [Events](#events).

Abandoned Cart 5 sends emails from its own background run, so any 4.x reminder jobs still waiting in Craft's queue finish without sending anything after the upgrade. A cart whose second reminder was waiting moves into Everyone Else and receives email 2 from there, as described in [What the Upgrade Converts](#what-the-upgrade-converts). A cart whose first reminder was still waiting isn't emailed.

### Settings

The 4.x settings are replaced. The migration converts the values saved in the control panel and the values in `config/abandoned-cart.php`; where both set a value, the file's wins, as it did in 4.x. It can't edit the file itself, so once the upgrade has run, update it using this mapping:

| v4 | v5 |
| --- | --- |
| `pluginName` | `pluginName` |
| `recoveryUrl` | `returnUrl` |
| `restoreExpiryHours` (hours) | `recoveryWindowMinutes` (minutes) |
| `recipientCooldownHours` (hours) | `contactLimitMinutes` (minutes) |
| `previousOrderRequired` | `returningCustomersOnly` |
| `blacklist` | `blockedDomains` for domains; email addresses move to Do Not Email |
| `staleRecordRetentionDays` | `historyRetentionDays` |
| `maxEnrollmentsPerRun` | `maxEnrollmentsPerRun` |
| `firstReminderDelay`, `secondReminderDelay` | The **Send** delay on the Everyone Else emails |
| `firstReminderSubject`, `secondReminderSubject` | The **Subject** on the Everyone Else emails |
| `firstReminderTemplate`, `secondReminderTemplate` | An [email template](../feature-tour/emails-and-email-templates.md#email-templates) chosen on each email |
| `discountCode` | The **Discount Code** on each Everyone Else email |
| `disableSecondReminder` | Delete the second email from Everyone Else |
| `passKey` | `passKey`, used only by the [HTTP trigger](#http-trigger) while compatibility mode is on |
| `includeBlacklisted` | Removed |

Durations in Abandoned Cart 5 are always in minutes. The recovery window is also measured differently: it now starts when the cart is abandoned, not when an email is sent, so it needs to cover the whole sequence.

Settings for individual emails are no longer configuration settings. Edit them on the sequence page, where editors can change them without a deployment. Remove `firstReminderSubject`, `secondReminderSubject`, `firstReminderDelay`, `secondReminderDelay`, `firstReminderTemplate`, `secondReminderTemplate`, `discountCode` and `disableSecondReminder` from your configuration file, along with the other removed settings. Abandoned Cart 5 ignores them, so leaving them in place only suggests they still do something.

Some sites built their subjects in the configuration file, for example by reading a global set through `Craft::$app->getGlobals()` so editors could change the wording. Remove that code too: a configuration file that loads elements runs on every request, and editors can now change each email's subject directly on the sequence page. Check that the Everyone Else emails have the subjects you expect after upgrading, and correct them there. If the subject differed per site, create a sequence for each site, or translate the subject with Twig as described in [Emails and Email Templates](../feature-tour/emails-and-email-templates.md#twig-in-subjects-and-preview-text).

::: code-group
```php [v4]
<?php

return [
    'restoreExpiryHours' => 72,
    'recipientCooldownHours' => 48,
    'recoveryUrl' => 'cart',
    'blacklist' => '@example.com, sam@example.net',
    'previousOrderRequired' => true,
    'firstReminderDelay' => 2,
    'discountCode' => 'COMEBACK10',
];
```

```php [v5]
<?php

return [
    'recoveryWindowMinutes' => 4320,
    'contactLimitMinutes' => 2880,
    'returnUrl' => 'cart',
    'blockedDomains' => ['example.com'],
    'returningCustomersOnly' => true,
    // The email delay and discount code are set on each email in
    // Abandoned Carts → Sequences → Everyone Else. Add sam@example.net
    // to Do Not Email from the Customers screen.
];
```
:::

### Reminder Timing

Abandoned Cart 4 described both reminder delays as hours after the cart was abandoned. The migration keeps that meaning. Email 1 waits the first delay after the cart is abandoned, and because each email in a sequence is timed from the one before it, email 2 waits the second delay minus the first.

With the 4.x defaults of 1 and 12 hours:

| Email | Sequence Setting | Sent After the Cart Is Abandoned |
| --- | --- | --- |
| Email 1 | 1 hour after the cart is abandoned | 1 hour |
| Email 2 | 11 hours after email 1 | 12 hours |

A cart counts as abandoned after an hour without changes, as in 4.x, so email 1 arrives about two hours after the customer last touched their cart. Some 4.x sites saw the second reminder arrive later than its setting suggested, because it waited for a separate scheduling pass. If customers were used to that later timing, increase email 2's delay on the Everyone Else page.

If your Commerce **Active Cart Duration** isn't an hour, set **Abandoned After** in **Settings → General Settings** to match, because Abandoned Cart 5 uses its own setting.

### Console Command

The scheduler command is now `abandoned-cart/recoveries/run`. It finds abandoned carts and sends the emails that are due in one run, without queueing a job per email. Update your cron job:

::: code-group
```shell [v4]
*/5 * * * * /path/to/php /path/to/project/craft abandoned-cart/reminders/schedule-emails
```

```shell [v5]
*/5 * * * * /path/to/php /path/to/project/craft abandoned-cart/recoveries/run
```
:::

While compatibility mode is on, `abandoned-cart/reminders/schedule-emails` runs the new command and logs a deprecation warning. With compatibility mode off, it exits with an error.

### HTTP Trigger

While [compatibility mode](#compatibility-mode) is on, the `abandoned-cart/carts/find-carts` endpoint keeps working, so a scheduled request that called it in 4.x carries on sending. The upgrade keeps your `passKey`, and the request works as it did in 4.x: a POST with the key in an `Authorization: Bearer` header. It now runs the 5.0 scheduler and responds with a JSON summary of the run instead of redirecting to the control panel. Each call logs a deprecation warning.

Switch to the [console command](#console-command) from cron when you can. If you can't use cron, Abandoned Cart also queues a run from ordinary site and control panel requests while a sequence is on, as long as Craft's queue is running. [Sending and Scheduling](../feature-tour/sending-and-scheduling.md#scheduling-the-run) explains both options. Once nothing calls the endpoint, remove the `passKey` from your configuration file and any secrets store. With compatibility mode off, the endpoint returns a 404.

### Email Templates

Custom reminder templates keep working as email templates, but they receive different variables. While [compatibility mode](#compatibility-mode) is on, the three 4.x variables are still provided alongside the new ones:

| v4 | v5 |
| --- | --- |
| `checkoutLink` | `recoverUrl` |
| `discount` | `discountCode` |
| `currentSite` | `site` |
| `order` | `order` |

`recoverUrl` is the complete recovery link, so use it on its own, without joining it to `siteUrl`. `discount` was `false` when there was no code; `discountCode` is `null`.

`order` is still the Commerce order when a real email is sent, but it's `null` in control panel previews and tests that use the sample cart. Guard anything that reads from it, or use the `cart` variable, which is always available.

Every recovery email now includes an unsubscribe link, and your template should show it with `unsubscribeUrl`. The editor's wording is available as `heading`, `message` and `buttonLabel`, so you can move hard-coded text out of the template and into the sequence.

::: code-group
```twig [v4]
<p>{{ 'It looks like you left some items in your cart.' | t }}</p>

{% if discount %}
    <p>Use <strong>{{ discount }}</strong> at checkout.</p>
{% endif %}

<a href="{{ siteUrl ~ checkoutLink }}">{{ 'Complete my order' | t }}</a>

<p>{{ currentSite.name }}</p>
```

```twig [v5]
<h1>{{ heading }}</h1>
{{ message }}

{% if discountCode %}
    <p>Use <strong>{{ discountCode }}</strong> at checkout.</p>
{% endif %}

<a href="{{ recoverUrl }}">{{ buttonLabel }}</a>

<p>{{ site.name }} · <a href="{{ unsubscribeUrl }}">Unsubscribe</a></p>
```
:::

The 4.x bundled templates, `abandoned-cart/emails/first` and `abandoned-cart/emails/second`, are removed. Emails that used them now use the Default email template and its bundled design, and a custom template that extended or included them needs rewriting. [Custom Email Templates](../template-guides/custom-email-templates.md) lists every variable.

A 4.x template path could contain Twig, such as a site handle, to choose a template per site. Email template paths are plain paths. Create an email template for each file and a sequence for each site instead.

Subjects carried over from 4.x can still contain Twig, which renders with `order`. The other 4.x variables aren't available in subjects; use tokens such as `{firstName}` and `{discountCode}` instead.

### Events

The `Carts::EVENT_BEFORE_SAVE_CART` and `Carts::EVENT_AFTER_SAVE_CART` events are removed, along with the `CartEvent` class and the 4.x cart model, because Abandoned Cart no longer stores carts that way. Listen to the event for the moment you care about instead, such as [`afterEnroll`](../developers/events.md#the-afterenroll-event) when a cart enters a sequence or [`afterRecover`](../developers/events.md#the-afterrecover-event) when it's recovered:

::: code-group
```php [v4]
use verbb\abandonedcart\events\CartEvent;
use verbb\abandonedcart\services\Carts;
use yii\base\Event;

Event::on(Carts::class, Carts::EVENT_AFTER_SAVE_CART, function(CartEvent $event) {
    if ($event->isNew) {
        MyModule::getInstance()->crm->trackAbandonedCart($event->cart->orderId);
    }
});
```

```php [v5]
use verbb\abandonedcart\events\RecoveryEvent;
use verbb\abandonedcart\services\Recoveries;
use yii\base\Event;

Event::on(Recoveries::class, Recoveries::EVENT_AFTER_ENROLL, function(RecoveryEvent $event) {
    MyModule::getInstance()->crm->trackAbandonedCart($event->recovery->orderId);
});
```
:::

`Carts::EVENT_BEFORE_MAIL_SEND` is deprecated. It still runs while compatibility mode is on, after the new event, and logs a deprecation warning. Move listeners to `Attempts::EVENT_BEFORE_SEND`, which gives you the message along with the cart's recovery, the email and its rendered content:

::: code-group
```php [v4]
use verbb\abandonedcart\events\BeforeMailSend;
use verbb\abandonedcart\services\Carts;
use yii\base\Event;

Event::on(Carts::class, Carts::EVENT_BEFORE_MAIL_SEND, function(BeforeMailSend $event) {
    $event->message->setBcc('sales@example.com');

    if ($event->order->total < 10) {
        $event->isValid = false;
    }
});
```

```php [v5]
use verbb\abandonedcart\events\SendEvent;
use verbb\abandonedcart\services\Attempts;
use yii\base\Event;

Event::on(Attempts::class, Attempts::EVENT_BEFORE_SEND, function(SendEvent $event) {
    $event->message->setBcc('sales@example.com');

    if ($event->recovery->getOrder()?->total < 10) {
        $event->isValid = false;
    }
});
```
:::

Cancelling either event skips that one email. To keep low-value carts out of recovery altogether, give a sequence a Cart Value audience, or cancel the [`beforeEnroll`](../developers/events.md#the-beforeenroll-event) event instead.

## What the Upgrade Converts

The migration runs when you update the plugin. On environments that receive the change through project config, the email templates and settings arrive with it.

**Your reminders become the Everyone Else sequence.** It has two emails, or one if the second reminder was disabled. Each email takes its subject, its delay (converted as described in [Reminder Timing](#reminder-timing)) and the discount code from your 4.x settings. The heading, message and button label come from Abandoned Cart's standard wording, because 4.x kept that text inside its templates. Read through each email on the Everyone Else page.

**Templates become email templates.** A **Default** template using the bundled design is created. Each custom 4.x reminder template becomes an email template called **4.x Reminder 1** or **4.x Reminder 2**, pointing at the same file, and the matching email uses it. Emails that used the 4.x bundled templates use Default.

**Your blocklist is split.** Domains in the blocklist become **Blocked Domains**. Email addresses become customers with the **Do not email** status, noted as "On the 4.x blocklist".

**Other settings carry over.** **Only Returning Customers**, **Return Page**, **Recovery Ends After** and **Contact Limit** take the matching 4.x values. **Keep History For** takes the 4.x retention only if you'd changed it from 90 days; otherwise it uses the new default of 365 days.

**Sending continues without a gap.** If 4.x sent a reminder in the 30 days before the upgrade, Everyone Else starts switched on and picks up carts that become abandoned from the moment of the upgrade. Older carts aren't swept in. If 4.x wasn't sending, Everyone Else starts off.

**Carts part-way through 4.x carry on.** A cart that received the first 4.x reminder but not the second, and is still incomplete and within its recovery window, moves into Everyone Else at email 2. Email 2 is timed from when the 4.x reminder was sent; if that time has already passed, it goes out on the first run.

**History is imported.** Every 4.x cart that was sent a reminder or recovered appears on the Carts screen and in the Overview, marked **Imported from 4.x**. Abandoned Cart 4 only recorded when the latest reminder was sent, so when both were sent, the first one's time is estimated. Recovered carts count as recovered, as do orders completed within the attribution window after the last reminder.

**Old links keep working.** Recovery links in emails customers already have restore their cart until its recovery period ends, regardless of compatibility mode.

## Behaviour Changes

These changes need no action, but they affect what customers and staff see:

- **Permissions:** staff with only **Access Abandoned Cart** can view every screen but can't change sequences or send emails. Give them **Manage sequences and send emails**, **Manage Do Not Email** or **View System Status** as their role needs. [Permissions](../feature-tour/permissions.md) describes each one.
- **Recovered orders:** an order counts as recovered when it's completed within the attribution window after an email, five days by default, even if the customer didn't click. Expect more recoveries in the Overview than 4.x reported.
- **Contact limit:** only emails that were actually sent count towards it, and a customer's newer cart replaces an older one still in recovery.
- **Recovery links:** a cart that belongs to an account with a password only restores for that customer, after signing in. Expired and invalid links show a page explaining what happened, instead of redirecting with an error message. The restored notice reads "Welcome back. Your cart is just as you left it."
- **Unsubscribing:** every email includes an unsubscribe link and one-click unsubscribe headers. Customers who unsubscribe are skipped from then on.
- **Control panel:** the 4.x dashboard is replaced by the Overview and Carts screens, at `abandoned-cart` and `abandoned-cart/carts`.

## Compatibility Mode

Compatibility mode keeps the developer-facing parts of Abandoned Cart 4 working while you update them. It's on by default and covers:

- the `checkoutLink`, `discount` and `currentSite` template variables
- the `Carts::EVENT_BEFORE_MAIL_SEND` event
- the `abandoned-cart/reminders/schedule-emails` console command
- the `abandoned-cart/carts/find-carts` scheduler URL

`checkoutLink` is the recovery link relative to the site's URL, so a 4.x link written as `{{ siteUrl ~ checkoutLink }}`, as in the 4.x bundled templates, keeps working. Its replacement, `recoverUrl`, is the complete link. Emails render as the cart's own site, so `siteUrl` and `checkoutLink` match on multi-site installs too.

The event, console command and scheduler URL log a deprecation warning each time they're used; open **Utilities → Deprecation Warnings** after the site has sent some emails and run the scheduler. The template variables don't log warnings, so search your email templates for `checkoutLink`, `discount` and `currentSite` and replace them as shown in [Email Templates](#email-templates). Once nothing uses them, switch compatibility mode off in your configuration file:

```php
<?php

return [
    'compatibilityMode' => false,
];
```

Recovery links in emails sent by 4.x keep working whether compatibility mode is on or off.

## Checking the Upgrade

After upgrading, work through these checks:

1. Update your cron job to `abandoned-cart/recoveries/run`, and update your configuration file if you have one.
2. Open **Abandoned Carts → Sequences → Everyone Else**. Read each email, check its delay and discount code, and send yourself a test.
3. If you had custom templates, preview an email that uses one with a real cart, and replace `checkoutLink`, `discount` and `currentSite` with the new variables.
4. Open **Settings → System Status** and check that the scheduler has run and every email template can be found.
5. Check **Abandoned Carts → Customers → Do not email** for the addresses from your 4.x blocklist.
6. Once Craft's deprecation warnings no longer list Abandoned Cart and your templates no longer use the 4.x variables, switch off compatibility mode.
