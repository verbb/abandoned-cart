# Upgrading from v4

Abandoned Cart 5 replaces the first and second reminders with sequences of up to five emails, and adds email templates, customer records, a Carts screen and reporting. This guide covers upgrading from Abandoned Cart 4 on Craft 5 and Commerce 5. A site still on Abandoned Cart 3 and Craft 4 can upgrade straight to Abandoned Cart 5 as part of moving to Craft 5, and the same conversion runs.

The upgrade's migration converts your settings, reminders and history automatically, and [What the Upgrade Converts](#what-the-upgrade-converts) describes the result. Some things can't be changed for you, such as your configuration file, cron jobs and custom code, and those are listed first.

## Breaking Changes

Review each of these that applies to your site:

- Every site needs Craft 5.6 or later. See [Craft Version](#craft-version).
- Every site's two reminders become the default sequence, **All Other Carts**, and their timing is converted. Check the [reminder timing](#reminder-timing) and the email wording after upgrading.
- Sites where 4.x was sending keep sending straight after the upgrade, with the default sequence's standard wording, in each environment. See [Sending After the Upgrade](#sending-after-the-upgrade).
- Sites with an `abandoned-cart.php` configuration file need to update it, because the 4.x settings are converted but not read afterwards. Use the [settings mapping](#settings).
- Sites with custom reminder templates get new template variables, and the 4.x bundled templates are removed. See [Email Templates](#email-templates).
- Sites whose 4.x template paths contain Twig, such as a site handle, can't send any email that uses them until the path is fixed. See [Template Paths Containing Twig](#template-paths-containing-twig).
- Sites where staff who aren't admins use the plugin need to grant them new permissions. See [Permissions](#permissions).
- Modules and plugins that call Abandoned Cart's 4.x services, or listen to its cart save events, need updating. See [Removed Services and Events](#removed-services-and-events).

Abandoned Cart 5 sends emails from its own background run, so any 4.x reminder jobs still waiting in Craft's queue finish without sending anything after the upgrade. A cart whose second reminder was waiting moves into the default sequence and receives email 2 from there, as described in [What the Upgrade Converts](#what-the-upgrade-converts). A cart whose first reminder was still waiting isn't emailed.

The console command, the HTTP trigger and the `beforeMailSend` event keep working after the upgrade, because [compatibility mode](#compatibility-mode) is on by default. They're covered under [Deprecated Changes](#deprecated-changes).

### Craft Version

Abandoned Cart 5 requires Craft CMS 5.6 or later, while Abandoned Cart 4 ran on any Craft 5 release. If your site is on an earlier Craft 5 release, update Craft first, or update both in the same `composer update`. [Requirements](requirements.md) lists the other minimum versions.

### Sending After the Upgrade

If 4.x sent a reminder in the 30 days before the upgrade, the default sequence, **All Other Carts**, starts switched on, so sending carries on without a gap. It picks up carts that become abandoned from the moment of the upgrade, and a cart that was waiting for its second 4.x reminder is sent email 2 on the first background run if it's already due, as [Carts Part-Way Through 4.x](#carts-part-way-through-4-x) describes.

The emails use Abandoned Cart's standard preview text, heading, message and button label, because 4.x kept that text in its templates. Read them on the **All Other Carts** page straight after upgrading. The migration runs separately in each environment, and sequences are stored in the database, so wording you change after upgrading a staging site has to be changed again on production. [What Moves Between Environments](configuration.md#what-moves-between-environments) explains the split.

To hold production's emails until you've read them, set `deliveryPaused` in its configuration file before you deploy the upgrade. Abandoned Cart 4 ignores the setting, so it's safe to add early. Pointing it at an environment variable lets you switch sending back on without another deployment:

```php
<?php

return [
    'deliveryPaused' => '$ABANDONED_CART_PAUSED',
];
```

Carts keep entering the sequence while sending is paused, and their emails go out on the first run after you unpause, unless their recovery period has ended in the meantime.

### Settings

The 4.x settings are replaced. The migration converts the values saved in the control panel and the values in `config/abandoned-cart.php`. Where both set a value, the file's wins, as it did in 4.x. The migration can't edit the file itself, so once the upgrade has run, update it using this mapping:

| v4 | v5 |
| --- | --- |
| `pluginName` | `pluginName` |
| `recoveryUrl` | `returnUrl` |
| `restoreExpiryHours` (hours) | `recoveryWindowMinutes` (minutes) |
| `recipientCooldownHours` (hours) | `contactLimitMinutes` (minutes) |
| `previousOrderRequired` | `returningCustomersOnly` |
| `blacklist` | `blockedDomains` for domains, with email addresses moved to Do Not Email |
| `staleRecordRetentionDays` | `historyRetentionDays` |
| `maxEnrollmentsPerRun` | `maxEnrollmentsPerRun`, set in the configuration file only |
| `firstReminderDelay`, `secondReminderDelay` | The **Send** delay on the default sequence's emails |
| `firstReminderSubject`, `secondReminderSubject` | The **Subject** on the default sequence's emails |
| `firstReminderTemplate`, `secondReminderTemplate` | An [email template](../feature-tour/emails-and-email-templates.md#email-templates) chosen on each email |
| `discountCode` | The **Discount Code** on each of the default sequence's emails |
| `disableSecondReminder` | Delete the second email from the default sequence |
| `passKey` | `passKey`, used only by the [HTTP trigger](#http-trigger) while compatibility mode is on |
| `includeBlacklisted` | Removed |

Settings ending in `Minutes` take minutes, where the 4.x settings they replace took hours. `historyRetentionDays` is still in days. The recovery period is also measured differently. In 4.x it ran from the last reminder sent, and in Abandoned Cart 5 it starts when the cart is abandoned, so it needs to cover the whole sequence.

`maxEnrollmentsPerRun` was a control panel field in 4.x. Its converted value carries over, but you can only change it from the configuration file.

Settings for individual emails are no longer configuration settings. Edit them on the sequence page, where editors can change them without a deployment. Remove `firstReminderSubject`, `secondReminderSubject`, `firstReminderDelay`, `secondReminderDelay`, `firstReminderTemplate`, `secondReminderTemplate`, `discountCode` and `disableSecondReminder` from your configuration file, along with the other removed settings. Abandoned Cart 5 ignores them, so leaving them in place only suggests they still do something.

Some sites built their subjects in the configuration file, for example by reading a global set through `Craft::$app->getGlobals()` so editors could change the wording. Remove that code too, because a configuration file that loads elements runs on every request, and editors can now change each email's subject directly on the sequence page. Check that the default sequence's emails have the subjects you expect after upgrading, and correct them there. If the subject differed per site, create a sequence for each site, or translate the subject with Twig as described in [Emails and Email Templates](../feature-tour/emails-and-email-templates.md#twig-in-subjects-and-preview-text).

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
    // Abandoned Carts → Sequences → All Other Carts. Add sam@example.net
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

A cart counts as abandoned after an hour without changes, as in 4.x, so email 1 arrives about two hours after the customer last touched their cart. Some 4.x sites saw the second reminder arrive later than its setting suggested, because it waited for a separate scheduling pass. If customers were used to that later timing, increase email 2's delay on the All Other Carts page.

Abandoned Cart 4 used Commerce's `activeCartDuration` setting to decide when a cart was abandoned, and Abandoned Cart 5 uses its own **Abandoned After** setting, which defaults to an hour. If you changed `activeCartDuration` in `config/commerce.php`, set **Abandoned After** in **Abandoned Carts → Settings → General Settings** to match.

### Email Templates

Custom reminder templates keep working as email templates, but they receive different variables. While [compatibility mode](#compatibility-mode) is on, the three 4.x variables are still provided alongside the new ones:

| v4 | v5 |
| --- | --- |
| `checkoutLink` | `recoverUrl` |
| `discount` | `discountCode` |
| `currentSite` | `site` |
| `order` | `order` |

`recoverUrl` is the complete recovery link, so use it on its own, without joining it to `siteUrl`. `discount` was `false` when there was no code, and `discountCode` is `null`.

`order` is still the Commerce order when a real email is sent, but it's `null` in control panel previews and tests that use the sample cart. Guard anything that reads from it, or use the `cart` variable, which is always available.

Every recovery email is sent with one-click unsubscribe headers, but the unsubscribe link in the email body only appears if the template outputs it. The bundled design does. A custom template must show it with `unsubscribeUrl`. The editor's wording is available as `heading`, `message` and `buttonLabel`, so you can move hard-coded text out of the template and into the sequence.

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

Subjects carried over from 4.x can still contain Twig, which renders with `order`. The other 4.x variables aren't available in subjects. Use tokens such as `{firstName}` and `{discountCode}` instead.

#### Template Paths Containing Twig

A 4.x template path could contain Twig, such as `_emails/{{ currentSite.handle }}/first`, to choose a template per site. Email template paths are plain paths, and the migration copies a 4.x path into its **4.x Reminder 1** or **4.x Reminder 2** template without changing it. A path containing Twig can't be found, so every email using that template fails to send, and System Status reports the missing file. If 4.x was sending before the upgrade, **All Other Carts** is already on, so fix this straight away, or pause sending as described in [Sending After the Upgrade](#sending-after-the-upgrade) until it's fixed.

In **Abandoned Carts → Settings → Email Templates**, point the template at a plain path, such as `_emails/default/first`. Then create an email template for each other site's file, and a sequence for each site that needs one, limited to that site with **Sites**. Email templates are saved in project config, so make these changes on your development site and deploy them.

### Permissions

In 4.x, the only Abandoned Cart permission was access to the plugin. Abandoned Cart 5 adds permissions for the actions on its new screens, and staff with only **Access Abandoned Cart** can view the Overview, Carts, Customers and Sequences screens but can't change sequences or send emails. Give them **Manage sequences and send emails**, **Manage Do Not Email**, **Delete customers** or **View System Status** as their role needs. The plugin's **Settings** section stays admin-only, apart from System Status. [Permissions](../feature-tour/permissions.md) describes each one.

### Removed Services and Events

Abandoned Cart no longer stores carts in its own table, so the code built around that table is removed. The `abandonedcart_carts` table is dropped once the migration has imported its history.

- `AbandonedCart::$plugin->getCarts()` and `AbandonedCart::$plugin->getService()` are removed, along with methods such as `getAllCarts()`, `getCartById()`, `getCartByOrderId()`, `saveCart()`, `sendMail()`, `restoreCart()` and `markCartAsRecovered()`. Code that calls them throws an error.
- The `verbb\abandonedcart\models\Cart` model and its record are removed.
- The `Carts::EVENT_BEFORE_SAVE_CART` and `Carts::EVENT_AFTER_SAVE_CART` events are removed, along with the `CartEvent` class.

Read recovery data through the events instead. Listen to the event for the moment you care about, such as [`afterEnroll`](../developers/events.md#the-afterenroll-event) when a cart enters a sequence or [`afterRecover`](../developers/events.md#the-afterrecover-event) when it's recovered:

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

## Deprecated Changes

These still work while [compatibility mode](#compatibility-mode) is on, which it is by default, and each logs a deprecation warning when it's used. Move to the replacement before you switch compatibility mode off.

### Console Command

The background run's command is now `abandoned-cart/recoveries/run`. It finds abandoned carts and sends the emails that are due in one run, without queueing a job per email. Update your cron job:

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

While compatibility mode is on, the 4.x scheduler URL, `actions/abandoned-cart/carts/find-carts`, keeps working, so a scheduled request that called it in 4.x carries on sending. The upgrade keeps your `passKey`, and the request works as it did in 4.x, as a POST with the key in an `Authorization: Bearer` header. It now runs the 5.0 scheduler and responds with a JSON summary of the run instead of redirecting to the control panel. Each call logs a deprecation warning.

Switch to the [console command](#console-command) from cron when you can. If you can't use cron, Abandoned Cart also queues a run from ordinary site and control panel requests while a sequence is on, as long as Craft's queue is running. [Sending and Scheduling](../feature-tour/sending-and-scheduling.md#scheduling-the-run) explains both options. Once nothing calls the URL, remove the `passKey` from your configuration file and any secrets store. With compatibility mode off, the URL returns a 404.

### Events

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

Cancelling either event skips that one email. To keep low-value carts out of recovery altogether, give a sequence a **Cart Value** audience, or cancel the [`beforeEnroll`](../developers/events.md#the-beforeenroll-event) event instead.

## Compatibility Mode

Compatibility mode keeps the developer-facing parts of Abandoned Cart 4 working while you update them. It's on by default and covers:

- the `checkoutLink`, `discount` and `currentSite` template variables
- the `Carts::EVENT_BEFORE_MAIL_SEND` event
- the `abandoned-cart/reminders/schedule-emails` console command
- the `actions/abandoned-cart/carts/find-carts` scheduler URL

`checkoutLink` is the recovery link relative to the site's URL, so a 4.x link written as `{{ siteUrl ~ checkoutLink }}`, as in the 4.x bundled templates, keeps working. Its replacement, `recoverUrl`, is the complete link. Emails render as the cart's own site, so `siteUrl` and `checkoutLink` match on multi-site installs too.

The event, console command and scheduler URL log a deprecation warning each time they're used. Open **Utilities → Deprecation Warnings** after the site has sent some emails and the background run has happened a few times. The template variables don't log warnings, so search your email templates for `checkoutLink`, `discount` and `currentSite` and replace them as shown in [Email Templates](#email-templates). Once nothing uses them, switch compatibility mode off in your configuration file:

```php
<?php

return [
    'compatibilityMode' => false,
];
```

Recovery links in emails sent by 4.x keep working whether compatibility mode is on or off.

## What the Upgrade Converts

The migration runs when you update the plugin. On environments that receive the change through project config, the email templates and settings arrive with it.

### Reminders

Your reminders become the default sequence, All Other Carts. It has two emails, or one if the second reminder was disabled. Each email takes its subject, its delay (converted as described in [Reminder Timing](#reminder-timing)) and the discount code from your 4.x settings. The preview text, heading, message and button label come from Abandoned Cart's standard wording, because 4.x kept that text inside its templates. Read through each email on the All Other Carts page.

### Templates

A **Default** template using the bundled design is created. Each custom 4.x reminder template becomes an email template called **4.x Reminder 1** or **4.x Reminder 2**, pointing at the same file, and the matching email uses it. If both reminders used the same file, both emails share **4.x Reminder 1**. Emails that used the 4.x bundled templates use Default.

### Blocklist

Domains in the blocklist become **Blocked Domains**. Email addresses become customers with the **Do not email** status, noted as "On the 4.x blocklist".

### Other Settings

**Only Returning Customers**, **Return Page**, **Recovery Ends After** and **Contact Limit** take the matching 4.x values. **Keep History For** takes the 4.x retention only if you'd changed it from 90 days. Otherwise it uses the default of 365 days.

### Sending

If 4.x sent a reminder in the 30 days before the upgrade, All Other Carts starts switched on and picks up carts that become abandoned from the moment of the upgrade, so sending continues without a gap. Older carts aren't swept in. If 4.x wasn't sending, the sequence starts off.

### Carts Part-Way Through 4.x

A cart that received the first 4.x reminder but not the second, and is still incomplete and within its recovery period, moves into All Other Carts at email 2. Email 2 is timed from when the 4.x reminder was sent. If that time has already passed, it goes out on the first run.

### History

Every 4.x cart that was sent a reminder or recovered appears on the Carts screen and in the Overview. Its history on the cart's page starts with "Imported from 4.x reminder history", followed by the reminders 4.x sent. Abandoned Cart 4 only recorded when the latest reminder was sent, so when both were sent, the first one's time is estimated. Recovered carts count as recovered, as do orders completed within the attribution window after the last reminder. Other imported carts end with the reason that fits what happened, such as **Emails finished** or **Ordered after the attribution window**. A cart whose 4.x reminders stopped before the last one, without an order, ends as **Imported from 4.x**, because Abandoned Cart 4 didn't record why. The Overview leaves those carts out of **Why Carts Didn't Recover**.

### Recovery Links

Recovery links in emails customers already have restore their cart until its recovery period ends, regardless of compatibility mode.

## Behaviour Changes

These changes need no action, but they affect what customers and staff see.

Discount codes are applied for the customer. In 4.x, the code was only shown in the email, and customers had to enter it at checkout. In Abandoned Cart 5, a customer who follows an email's link has that email's **Discount Code** applied to their cart. If your templates or messages tell customers to enter the code, consider rewording them.

An order counts as recovered when the customer completes it within the attribution window after the cart's last email, five days by default, even if they didn't click, and even if they bought from a different cart. Any completed order by the same customer, matched by email address or account, also stops their remaining emails. Expect more recoveries in the Overview than 4.x reported.

A customer follows one cart at a time. When they abandon a second cart while an earlier one is in recovery, the newer cart takes over the earlier cart's recovery, keeping its sequence and the times of its remaining emails, and the earlier cart ends with the reason **Started a newer cart**. [Sequences](../feature-tour/sequences.md#when-a-customer-leaves-a-newer-cart) explains the takeover. Once a recovery has ended, the **Contact Limit** decides when another cart can start a new sequence, and only emails that were actually sent count towards it.

A cart that belongs to a customer who can sign in to their account only restores for that customer, after signing in. Expired and invalid links show a page explaining what happened, instead of redirecting with an error message. The restored notice reads "Welcome back. Your cart is just as you left it."

Every email carries one-click unsubscribe headers, and the bundled design includes an unsubscribe link. Customers who unsubscribe are skipped from then on.

The 4.x dashboard is replaced by the Overview and Carts screens, at `abandoned-cart` and `abandoned-cart/carts`.

## Checking the Upgrade

After upgrading, work through these checks:

1. Update your cron job to `abandoned-cart/recoveries/run`, and update your configuration file if you have one.
2. Open **Abandoned Carts → Sequences → All Other Carts** on each environment. Read each email, check its delay and discount code, and send yourself a test.
3. If you had custom templates, preview an email that uses one with a real cart, add the unsubscribe link, and replace `checkoutLink`, `discount` and `currentSite` with the new variables.
4. Open **Abandoned Carts → Settings → System Status** and check that the background run has happened and every email template can be found.
5. Check **Abandoned Carts → Customers → Do not email** for the addresses from your 4.x blocklist.
6. Give staff who aren't admins the permissions their roles need.
7. Once Craft's deprecation warnings no longer list Abandoned Cart and your templates no longer use the 4.x variables, switch off compatibility mode.
