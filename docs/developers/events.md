# Events

Abandoned Cart provides events for extending how carts are matched, emailed and recorded. Modules and plugins can register event listeners, typically in their `init()` methods, to modify Abandoned Cart's behaviour.

## Sequence Events

### The `beforeSaveSequence` Event

The event that is triggered before a sequence is saved, including when one is created from a preset or switched on or off. It runs before the sequence is validated. Set `$event->isValid` to `false` to stop the save; nothing is written, and the `afterSaveSequence` event isn't triggered.

```php
use verbb\abandonedcart\events\SequenceEvent;
use verbb\abandonedcart\services\Sequences;
use yii\base\Event;

Event::on(Sequences::class, Sequences::EVENT_BEFORE_SAVE_SEQUENCE, function(SequenceEvent $event) {
    $sequence = $event->sequence;
    $isNew = $event->isNew;
    // ...
});
```

### The `afterSaveSequence` Event

The event that is triggered after a sequence is saved.

```php
use verbb\abandonedcart\events\SequenceEvent;
use verbb\abandonedcart\services\Sequences;
use yii\base\Event;

Event::on(Sequences::class, Sequences::EVENT_AFTER_SAVE_SEQUENCE, function(SequenceEvent $event) {
    $sequence = $event->sequence;
    $isNew = $event->isNew;
    // ...
});
```

### The `beforeDeleteSequence` Event

The event that is triggered before a sequence is deleted. Set `$event->isValid` to `false` to keep the sequence. The default sequence can never be deleted, so this event isn't triggered for it.

```php
use verbb\abandonedcart\events\SequenceEvent;
use verbb\abandonedcart\services\Sequences;
use yii\base\Event;

Event::on(Sequences::class, Sequences::EVENT_BEFORE_DELETE_SEQUENCE, function(SequenceEvent $event) {
    $sequence = $event->sequence;
    // ...
});
```

### The `afterDeleteSequence` Event

The event that is triggered after a sequence is deleted. The sequence's emails are removed with it, without triggering the email delete events.

```php
use verbb\abandonedcart\events\SequenceEvent;
use verbb\abandonedcart\services\Sequences;
use yii\base\Event;

Event::on(Sequences::class, Sequences::EVENT_AFTER_DELETE_SEQUENCE, function(SequenceEvent $event) {
    $sequence = $event->sequence;
    // ...
});
```

## Preset Events

### The `registerPresets` Event

The event that is triggered when Abandoned Cart collects the sequence presets offered by **New Sequence**. `$event->presets` holds the presets from **Settings → Sequence Presets**, in their order, as `Preset` models; add your own, reorder them, change them or remove some. Presets you add are listed under Settings as **Added by Plugins** and can't be edited there. Changes you make to presets from Settings apply only while the listener runs; they don't change project config. Each preset's `emails` are arrays of email attributes, with an optional `emailTemplate` handle and an optional `offersDiscount` flag, which marks the email meant to carry a discount code with a green dot in the preset picker. A sequence created from a preset is a one-time copy, so later changes to a preset don't affect existing sequences.

```php
use verbb\abandonedcart\events\RegisterPresetsEvent;
use verbb\abandonedcart\models\Preset;
use verbb\abandonedcart\models\Sequence;
use verbb\abandonedcart\services\Presets;
use yii\base\Event;

Event::on(Presets::class, Presets::EVENT_REGISTER_PRESETS, function(RegisterPresetsEvent $event) {
    $event->presets[] = new Preset([
        'handle' => 'firstOrderDiscount',
        'name' => 'First Order Discount',
        'description' => 'A reminder, then 10% off for customers who haven’t ordered before.',
        'audienceType' => Sequence::AUDIENCE_FIRST_TIME,
        'emails' => [
            [
                'delayMinutes' => 60,
                'subject' => 'Your cart is saved, {firstName}',
                'heading' => 'You left something behind',
                'message' => 'Your cart is waiting whenever you’re ready.',
            ],
            [
                'delayMinutes' => 1440,
                'subject' => '10% off your first order',
                'heading' => 'A welcome gift',
                'message' => 'Use the code below at checkout.',
                'discountCode' => 'WELCOME10',
                'emailTemplate' => 'branded',
            ],
        ],
        // Recovery Default overrides, keyed by sequence attribute.
        'overrides' => [
            'recoveryWindowMinutes' => 4320,
        ],
    ]);
});
```

A preset needs a unique `handle` and a `name`; invalid presets are left out and logged. When a sequence is created, an `emailTemplate` handle that doesn't exist falls back to the default template, with a notice for the editor.

Abandoned Cart creates the default sequence from the `threeReminders` preset when the plugin is installed. If that preset has been deleted under Settings or removed by a listener, it uses the built-in Three Reminders instead.

## Email Events

### The `beforeSaveEmail` Event

The event that is triggered before an email in a sequence is saved. It runs before the email is validated. Set `$event->isValid` to `false` to stop the save; when the email is part of a sequence being created from a preset, the whole sequence is rolled back.

```php
use verbb\abandonedcart\events\EmailEvent;
use verbb\abandonedcart\services\Emails;
use yii\base\Event;

Event::on(Emails::class, Emails::EVENT_BEFORE_SAVE_EMAIL, function(EmailEvent $event) {
    $email = $event->email;
    $isNew = $event->isNew;
    // ...
});
```

### The `afterSaveEmail` Event

The event that is triggered after an email in a sequence is saved.

```php
use verbb\abandonedcart\events\EmailEvent;
use verbb\abandonedcart\services\Emails;
use yii\base\Event;

Event::on(Emails::class, Emails::EVENT_AFTER_SAVE_EMAIL, function(EmailEvent $event) {
    $email = $event->email;
    $isNew = $event->isNew;
    // ...
});
```

### The `beforeDeleteEmail` Event

The event that is triggered before an email is deleted from a sequence. Set `$event->isValid` to `false` to keep the email.

```php
use verbb\abandonedcart\events\EmailEvent;
use verbb\abandonedcart\services\Emails;
use yii\base\Event;

Event::on(Emails::class, Emails::EVENT_BEFORE_DELETE_EMAIL, function(EmailEvent $event) {
    $email = $event->email;
    // ...
});
```

### The `afterDeleteEmail` Event

The event that is triggered after an email is deleted from a sequence. Carts that were due to receive it won't.

```php
use verbb\abandonedcart\events\EmailEvent;
use verbb\abandonedcart\services\Emails;
use yii\base\Event;

Event::on(Emails::class, Emails::EVENT_AFTER_DELETE_EMAIL, function(EmailEvent $event) {
    $email = $event->email;
    // ...
});
```

## Email Template Events

Email templates are stored in project config. The before events are triggered where the change is made; the after events are triggered when the change is applied, which includes applying project config on another environment.

### The `beforeSaveEmailTemplate` Event

The event that is triggered before an email template is saved. Set `$event->isValid` to `false` to stop the save; nothing is written to project config.

```php
use verbb\abandonedcart\events\EmailTemplateEvent;
use verbb\abandonedcart\services\EmailTemplates;
use yii\base\Event;

Event::on(EmailTemplates::class, EmailTemplates::EVENT_BEFORE_SAVE_EMAIL_TEMPLATE, function(EmailTemplateEvent $event) {
    $template = $event->template;
    $isNew = $event->isNew;
    // ...
});
```

### The `afterSaveEmailTemplate` Event

The event that is triggered after an email template's project config change is applied.

```php
use verbb\abandonedcart\events\EmailTemplateEvent;
use verbb\abandonedcart\services\EmailTemplates;
use yii\base\Event;

Event::on(EmailTemplates::class, EmailTemplates::EVENT_AFTER_SAVE_EMAIL_TEMPLATE, function(EmailTemplateEvent $event) {
    $template = $event->template;
    $isNew = $event->isNew;
    // ...
});
```

### The `beforeDeleteEmailTemplate` Event

The event that is triggered before an email template is deleted. Set `$event->isValid` to `false` to keep it. The default template can't be deleted, so this event isn't triggered for it.

```php
use verbb\abandonedcart\events\EmailTemplateEvent;
use verbb\abandonedcart\services\EmailTemplates;
use yii\base\Event;

Event::on(EmailTemplates::class, EmailTemplates::EVENT_BEFORE_DELETE_EMAIL_TEMPLATE, function(EmailTemplateEvent $event) {
    $template = $event->template;
    // ...
});
```

### The `afterDeleteEmailTemplate` Event

The event that is triggered after an email template's deletion is applied. Emails that used it fall back to the default template.

```php
use verbb\abandonedcart\events\EmailTemplateEvent;
use verbb\abandonedcart\services\EmailTemplates;
use yii\base\Event;

Event::on(EmailTemplates::class, EmailTemplates::EVENT_AFTER_DELETE_EMAIL_TEMPLATE, function(EmailTemplateEvent $event) {
    $template = $event->template;
    // ...
});
```

## Audience Events

### The `registerAudiences` Event

The event that is triggered when Abandoned Cart collects the audiences shown under **Who Gets This Sequence**. `$event->audiences` is keyed by audience type, and each entry has a `label` and `description`. Change an entry to suit your store's wording, or add your own audience type. A sequence can be saved with any type in this list.

```php
use verbb\abandonedcart\events\RegisterAudiencesEvent;
use verbb\abandonedcart\models\Sequence;
use verbb\abandonedcart\services\Audiences;
use yii\base\Event;

Event::on(Audiences::class, Audiences::EVENT_REGISTER_AUDIENCES, function(RegisterAudiencesEvent $event) {
    $event->audiences[Sequence::AUDIENCE_RETURNING]['label'] = 'Loyal Customers';

    $event->audiences['wholesale'] = [
        'label' => 'Wholesale Customers',
        'description' => 'Customers with a wholesale account',
    ];
});
```

Abandoned Cart doesn't know how to match an audience type you add, so a registered audience matches no carts until a [`matchAudience`](#the-matchaudience-event) listener decides. The sequence editor has no settings fields for a registered audience, so it suits rules that need no settings in the control panel, such as the wholesale check below. When the sequence form posts values under `audienceSettings[...]` for a registered audience, they're saved as the sequence's `audienceSettings` and available to your listener as `$event->sequence->audienceSettings`.

### The `matchAudience` Event

The event that is triggered after Abandoned Cart decides whether a cart matches a sequence's audience. `$event->matches` holds the result, or `null` for an audience type Abandoned Cart doesn't recognise; set it to `true` or `false` to decide. A `null` result counts as no match. Carts from a site the sequence doesn't include never reach this event.

Use it to match a registered audience, or to refine a built-in one with information Abandoned Cart can't see. This listener matches the `wholesale` audience registered above to customers in the `wholesale` user group:

```php
use verbb\abandonedcart\events\MatchAudienceEvent;
use verbb\abandonedcart\services\Audiences;
use yii\base\Event;

Event::on(Audiences::class, Audiences::EVENT_MATCH_AUDIENCE, function(MatchAudienceEvent $event) {
    if ($event->sequence->audienceType !== 'wholesale') {
        return;
    }

    $customer = $event->order->getCustomer();
    $event->matches = $customer && $customer->isInGroup('wholesale');
});
```

To narrow a built-in audience instead, check the sequence's handle and combine your rule with `$event->matches`, such as `$event->matches = $event->matches && $customer?->isInGroup('vip');` for a **Custom Rules** sequence.

## Cart Recovery Events

### The `beforeEnroll` Event

The event that is triggered when an abandoned cart has passed every check and matched a sequence, before it enters that sequence. Set `$event->isValid` to `false` to keep the cart out of recovery. The cart is recorded as skipped with the reason **Excluded by a plugin**; set `$event->skipReason` to explain why on the cart's page.

```php
use verbb\abandonedcart\events\EnrollmentEvent;
use verbb\abandonedcart\services\Recoveries;
use yii\base\Event;

Event::on(Recoveries::class, Recoveries::EVENT_BEFORE_ENROLL, function(EnrollmentEvent $event) {
    $order = $event->order;
    $customer = $event->customer;
    $sequence = $event->sequence;

    if (str_ends_with((string)$order->getEmail(), '@wholesale.example.com')) {
        $event->isValid = false;
        $event->skipReason = 'Wholesale accounts are followed up by phone';
    }
});
```

### The `afterEnroll` Event

The event that is triggered after a cart enters a sequence and its emails are scheduled.

```php
use verbb\abandonedcart\events\RecoveryEvent;
use verbb\abandonedcart\services\Recoveries;
use yii\base\Event;

Event::on(Recoveries::class, Recoveries::EVENT_AFTER_ENROLL, function(RecoveryEvent $event) {
    $recovery = $event->recovery;
    $order = $recovery->getOrder();
    // ...
});
```

### The `afterSkip` Event

The event that is triggered after an abandoned cart is recorded as skipped. `$event->skippedCart->reason` holds the reason, such as `doNotEmail`, `noConsent` or `contactLimit`.

```php
use verbb\abandonedcart\events\SkippedCartEvent;
use verbb\abandonedcart\services\Recoveries;
use yii\base\Event;

Event::on(Recoveries::class, Recoveries::EVENT_AFTER_SKIP, function(SkippedCartEvent $event) {
    $skippedCart = $event->skippedCart;
    $reason = $skippedCart->reason;
    // ...
});
```

### The `resolveConsent` Event

The event that is triggered when Abandoned Cart checks whether a customer agreed to be emailed. `$event->hasConsent` holds the result from the consent field, or `true` when no consent field is set; change it to decide consent yourself. A customer without consent is skipped with the reason **Didn't opt in**. When a consent field is set, the event is also triggered as an unsubscribed customer completes an order, to decide whether they've opted in again.

```php
use verbb\abandonedcart\events\ConsentEvent;
use verbb\abandonedcart\services\Recoveries;
use yii\base\Event;

Event::on(Recoveries::class, Recoveries::EVENT_RESOLVE_CONSENT, function(ConsentEvent $event) {
    $order = $event->order;
    $customer = $event->customer;

    // `MyModule` and its `newsletter` service stand in for your own module's code.
    $event->hasConsent = MyModule::getInstance()->newsletter->isSubscribed($customer->email);
});
```

### The `afterRecover` Event

The event that is triggered after a completed order is credited to a cart's recovery. `$event->recovery->creditedAttemptId` identifies the email it was credited to.

```php
use verbb\abandonedcart\events\RecoveryEvent;
use verbb\abandonedcart\services\Recoveries;
use yii\base\Event;

Event::on(Recoveries::class, Recoveries::EVENT_AFTER_RECOVER, function(RecoveryEvent $event) {
    $recovery = $event->recovery;
    $orderTotal = $recovery->orderTotal;
    // ...
});
```

### The `afterEnd` Event

The event that is triggered after a cart's recovery ends without being recovered, such as when its emails finish, the customer unsubscribes or staff stop it. `$event->recovery->endReason` holds the reason, such as `finished`, `expired` or `newerCart`.

```php
use verbb\abandonedcart\events\RecoveryEvent;
use verbb\abandonedcart\services\Recoveries;
use yii\base\Event;

Event::on(Recoveries::class, Recoveries::EVENT_AFTER_END, function(RecoveryEvent $event) {
    $recovery = $event->recovery;
    $reason = $recovery->endReason;
    // ...
});
```

### The `defineUrls` Event

The event that is triggered when Abandoned Cart creates the recovery and unsubscribe links for an email. Change `$event->recoverUrl` or `$event->unsubscribeUrl` to alter them, for example to add campaign tracking parameters. Keep the original path and token, because they're what identify the cart and customer. The unsubscribe link is also used in the email's `List-Unsubscribe` header.

```php
use craft\helpers\UrlHelper;
use verbb\abandonedcart\events\DefineUrlsEvent;
use verbb\abandonedcart\services\Recoveries;
use yii\base\Event;

Event::on(Recoveries::class, Recoveries::EVENT_DEFINE_URLS, function(DefineUrlsEvent $event) {
    $position = $event->attempt?->position;

    $event->recoverUrl = UrlHelper::urlWithParams($event->recoverUrl, [
        'utm_source' => 'abandoned-cart',
        'utm_medium' => 'email',
        'utm_campaign' => 'email-' . $position,
    ]);
});
```

## Sending Events

### The `beforeSend` Event

The event that is triggered before each recovery email is sent, after it has been rendered. `$event->message` is the Craft mail message. Change it, such as adding a header or a BCC address, or replace it with a message of your own, which is sent instead. Set `$event->isValid` to `false` to skip this email. It's marked **Not sent** with the reason "a plugin or module cancelled it", and the cart's later emails are still sent. Test emails sent from the control panel don't trigger this event.

```php
use verbb\abandonedcart\events\SendEvent;
use verbb\abandonedcart\services\Attempts;
use yii\base\Event;

Event::on(Attempts::class, Attempts::EVENT_BEFORE_SEND, function(SendEvent $event) {
    $recovery = $event->recovery;
    $email = $event->email;
    $rendered = $event->rendered;

    $event->message->setHeader('X-Campaign', 'abandoned-cart-' . $event->attempt->position);
});
```

### The `afterSend` Event

The event that is triggered after Abandoned Cart tries to send a recovery email. `$event->success` says whether the mailer accepted it, and `$event->attempt` holds the updated status. `$event->message` is the message that was sent, or tried. A failed email is retried automatically until it has had three tries, and this event is triggered for each try. `$event->rendered` and `$event->message` are `null` when rendering itself failed.

```php
use Craft;
use verbb\abandonedcart\events\SendEvent;
use verbb\abandonedcart\services\Attempts;
use yii\base\Event;

Event::on(Attempts::class, Attempts::EVENT_AFTER_SEND, function(SendEvent $event) {
    $attempt = $event->attempt;
    $success = $event->success;

    if (!$success) {
        Craft::warning("Recovery email {$attempt->position} failed: {$attempt->failureReason}", 'my-module');
    }
});
```

## Email Rendering Events

### The `defineVariables` Event

The event that is triggered when Abandoned Cart prepares the variables for an email template, including for previews and tests. Add to or change `$event->variables` to make more data available to bundled and custom templates. `$event->order` is `null` when the email is rendered with the sample cart.

```php
use verbb\abandonedcart\events\DefineEmailVariablesEvent;
use verbb\abandonedcart\services\EmailRenderer;
use yii\base\Event;

Event::on(EmailRenderer::class, EmailRenderer::EVENT_DEFINE_VARIABLES, function(DefineEmailVariablesEvent $event) {
    $event->variables['supportPhone'] = '1300 000 000';
});
```

Tokens such as `{firstName}` in the email's wording aren't affected; this event only adds Twig variables for templates.

### The `resolveLanguage` Event

The event that is triggered when Abandoned Cart chooses the language to render an email in, for real emails, previews and tests. `$event->language` holds the order's language, or the language of the cart's site when the order doesn't have one; change it to render the email in another language. The language applies to the bundled design's text, to anything your template passes through the `t` filter, and to Twig in the email's subject and preview text.

`$event->cart` is the cart summary the email is rendered with, and `$event->order` is the Commerce order, or `null` for the sample cart. This example uses a language chosen by the customer, stored in a Dropdown field with the handle `preferredLanguage` on their user account:

```php
use verbb\abandonedcart\events\ResolveLanguageEvent;
use verbb\abandonedcart\services\EmailRenderer;
use yii\base\Event;

Event::on(EmailRenderer::class, EmailRenderer::EVENT_RESOLVE_LANGUAGE, function(ResolveLanguageEvent $event) {
    $preferred = (string)($event->order?->getCustomer()?->preferredLanguage ?? '');

    if ($preferred !== '') {
        $event->language = $preferred;
    }
});
```

The email's wording is still the wording written on the sequence. To send different wording per language, give each site its own sequence, or translate the subject with Twig as described in [Emails and Email Templates](../feature-tour/emails-and-email-templates.md#twig-in-subjects-and-preview-text).

## Customer Events

### The `beforeSaveCustomer` Event

The event that is triggered before a customer record is saved. Set `$event->isValid` to `false` to stop the save. Abandoned Cart creates customers while it processes carts. If you cancel a new customer (`$event->isNew`), that cart is skipped with the reason **Excluded by a plugin** and the run carries on with the others.

```php
use verbb\abandonedcart\events\CustomerEvent;
use verbb\abandonedcart\services\Customers;
use yii\base\Event;

Event::on(Customers::class, Customers::EVENT_BEFORE_SAVE_CUSTOMER, function(CustomerEvent $event) {
    $customer = $event->customer;
    $isNew = $event->isNew;
    // ...
});
```

### The `afterSaveCustomer` Event

The event that is triggered after a customer record is saved.

```php
use verbb\abandonedcart\events\CustomerEvent;
use verbb\abandonedcart\services\Customers;
use yii\base\Event;

Event::on(Customers::class, Customers::EVENT_AFTER_SAVE_CUSTOMER, function(CustomerEvent $event) {
    $customer = $event->customer;
    $isNew = $event->isNew;
    // ...
});
```

### The `beforeChangeStatus` Event

The event that is triggered before a customer's email status changes, such as when they unsubscribe, change their preference from their account, or staff add them to Do Not Email. `$event->status` is the new status (`active`, `unsubscribed` or `doNotEmail`), and `$event->source` says what caused it (`unsubscribe`, `account`, `staff`, `checkout` or `upgrade`). Set `$event->isValid` to `false` to keep the current status; recoveries that are under way carry on.

```php
use verbb\abandonedcart\events\CustomerStatusEvent;
use verbb\abandonedcart\services\Customers;
use yii\base\Event;

Event::on(Customers::class, Customers::EVENT_BEFORE_CHANGE_STATUS, function(CustomerStatusEvent $event) {
    $customer = $event->customer;
    $status = $event->status;
    $source = $event->source;
    $note = $event->note;
    // ...
});
```

### The `afterChangeStatus` Event

The event that is triggered after a customer's email status changes, and after any recoveries under way have ended. Use it to keep another system in step, such as unsubscribing the customer from a newsletter list too.

```php
use verbb\abandonedcart\events\CustomerStatusEvent;
use verbb\abandonedcart\models\Customer;
use verbb\abandonedcart\services\Customers;
use yii\base\Event;

Event::on(Customers::class, Customers::EVENT_AFTER_CHANGE_STATUS, function(CustomerStatusEvent $event) {
    if ($event->status === Customer::STATUS_UNSUBSCRIBED) {
        // `MyModule` and its `newsletter` service stand in for your own module's code.
        MyModule::getInstance()->newsletter->unsubscribe($event->customer->email);
    }
});
```

## Deprecated Events

### The `beforeMailSend` Event

The event that is triggered before each recovery email is sent, after the `beforeSend` event, while the [`compatibilityMode`](../get-started/configuration.md#compatibilitymode) setting is on. It's deprecated, so use the [`beforeSend` event](#the-beforesend-event) instead. [Upgrading from v4](../get-started/upgrading-from-v4.md#events) shows how to move a listener across.

```php
use verbb\abandonedcart\events\BeforeMailSend;
use verbb\abandonedcart\services\Carts;
use yii\base\Event;

Event::on(Carts::class, Carts::EVENT_BEFORE_MAIL_SEND, function(BeforeMailSend $event) {
    $order = $event->order;
    $message = $event->message;
    // ...
});
```
