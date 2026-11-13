# Custom Presets

A preset is a starting point offered by **New Sequence**, with an audience, up to five emails and any Recovery Default overrides already filled in. Admins can manage presets under **Abandoned Carts → Settings → Sequence Presets**, as [Sequences](../feature-tour/sequences.md#managing-presets) explains. Add a preset from a module or plugin when it should ship with your code and stay the same across environments, such as one built around a [custom audience](custom-audiences.md). You can also change, reorder or remove the presets that come from Settings.

## Create the Preset

A preset is a `verbb\abandonedcart\models\Preset` model. This one sends a reminder after an hour, then a discount code a day later, to customers who haven’t ordered before.

```php
use verbb\abandonedcart\models\Preset;
use verbb\abandonedcart\models\Sequence;

$preset = new Preset([
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
            'message' => 'Your code is added when you return to your cart.',
            'discountCode' => 'WELCOME10',
            'emailTemplate' => 'branded',
        ],
    ],
    // Recovery Default overrides, keyed by sequence attribute.
    'overrides' => [
        'recoveryWindowMinutes' => 4320,
    ],
]);
```

## Register the Preset

Abandoned Cart finds presets through the `registerPresets` event. Register your preset from your module or plugin’s `init()` method.

```php
use verbb\abandonedcart\events\RegisterPresetsEvent;
use verbb\abandonedcart\services\Presets;
use yii\base\Event;

Event::on(Presets::class, Presets::EVENT_REGISTER_PRESETS, function(RegisterPresetsEvent $event) use ($preset) {
    $event->presets[] = $preset;
});
```

`$event->presets` already holds the presets from Settings, in their order, as `Preset` models. Add yours at the position it should appear in **New Sequence**. Presets you add are listed under Settings as **Added by Plugins** and can’t be edited there.

## Preset Settings

::: reference
### `handle`

**Type:** `string`

The preset’s handle. It’s required, and each preset needs its own, because when two presets share a handle, the later one replaces the earlier one without a log entry.
:::

::: reference
### `name`

**Type:** `string`

The name shown in **New Sequence**, and the name given to sequences created from the preset. It’s required.
:::

::: reference
### `description`

**Type:** `string|null`

A short description shown with the preset in **New Sequence**.
:::

::: reference
### `audienceType`

**Type:** `string`

The audience new sequences start with, such as `Sequence::AUDIENCE_FIRST_TIME`. It must be a built-in audience or one added with the [`registerAudiences`](events.md#the-registeraudiences-event) event. Defaults to `Sequence::AUDIENCE_ALL`.
:::

::: reference
### `audienceSettings`

**Type:** `array`

The audience’s settings, such as the amount for a cart value audience.
:::

::: reference
### `emails`

**Type:** `array`

Each email is an array of email attributes, such as `delayMinutes`, `subject`, `previewText`, `heading`, `message`, `buttonLabel` and `discountCode`. An email can also have an `emailTemplate` handle, and an `offersDiscount` flag that marks it as the email meant to carry a discount code in the preset picker. An email with a `discountCode` is marked the same way. Only the first five emails are used.
:::

::: reference
### `overrides`

**Type:** `array`

The Recovery Defaults that new sequences override, keyed by sequence attribute, such as `recoveryWindowMinutes`.
:::

## Change the Presets from Settings

A listener can also change, reorder or remove the presets in `$event->presets`. These changes aren’t saved to project config, so they last only as long as the listener is registered.

```php
use verbb\abandonedcart\events\RegisterPresetsEvent;
use verbb\abandonedcart\services\Presets;
use yii\base\Event;

Event::on(Presets::class, Presets::EVENT_REGISTER_PRESETS, function(RegisterPresetsEvent $event) {
    // Hide the Single Reminder preset
    $event->presets = array_values(array_filter($event->presets, fn($preset) => $preset->handle !== Presets::PRESET_SINGLE_REMINDER));
});
```

## How Presets Are Used

Presets that are missing a `handle` or `name`, or whose `audienceType` isn’t registered, are left out and logged. A sequence created from a preset is a one-time copy that starts switched off, so later changes to a preset don’t affect existing sequences. When a sequence is created, an `emailTemplate` handle that doesn’t exist falls back to the default template, with a notice for the editor.

Abandoned Cart creates the default sequence from the `threeReminders` preset when the plugin is installed or upgraded. If that preset has been deleted under Settings or removed by a listener, it uses the built-in Three Reminders instead.
