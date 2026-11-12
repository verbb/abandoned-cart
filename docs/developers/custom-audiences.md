# Custom Audiences

A sequence's audience decides which abandoned carts it takes. Abandoned Cart has six built-in audiences, and **Custom Rules** accepts any Commerce order condition, so check those first. A custom audience is for a rule that depends on something they can't see, such as a user group your store uses for trade accounts or data another plugin keeps about the customer.

This page builds a **Wholesale Customers** audience that takes carts from customers in a `wholesale` user group. It assumes you have a module or plugin to put the code in. The listeners belong in its `init()` method.

## Register the Audience

Abandoned Cart collects its audiences with the [`registerAudiences`](events.md#the-registeraudiences-event) event. Each audience is keyed by a type handle you choose, and has a `label` and `description` that appear under **Who Gets This Sequence** on the sequence page.

```php
use verbb\abandonedcart\events\RegisterAudiencesEvent;
use verbb\abandonedcart\services\Audiences;
use yii\base\Event;

Event::on(Audiences::class, Audiences::EVENT_REGISTER_AUDIENCES, function(RegisterAudiencesEvent $event) {
    $event->audiences['wholesale'] = [
        'label' => 'Wholesale Customers',
        'description' => 'Customers in the Wholesale user group',
    ];
});
```

Once this listener runs, editors can choose **Wholesale Customers** for a sequence, and administrators can choose it for a preset under **Abandoned Carts → Settings → Sequence Presets**. Keep the type handle the same once sequences use it, because it's saved with each sequence.

## Decide Which Carts Match

Abandoned Cart doesn't know what your audience means, so on its own it matches no carts. The [`matchAudience`](events.md#the-matchaudience-event) event lets you decide. It's triggered for each sequence a cart is checked against, with the sequence, the cart (a Commerce order) and Abandoned Cart's own result, which is `null` for an audience it doesn't recognise.

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

Return early for every other audience type, so your listener leaves the built-in audiences alone. A guest cart has no user account, so `getCustomer()` can return `null`, and the example treats that as no match. Carts from a site the sequence doesn't include are turned away before this event, so you don't need to check the site.

The same event runs when someone uses **Try It With a Real Cart** on the sequence page, so keep the listener free of side effects such as saving data or sending messages.

## Use the Audience in a Sequence

Create a sequence, choose **Wholesale Customers** under **Who Gets This Sequence**, and save. A custom audience has no settings fields on the sequence page, which is why the rule lives in your listener. The sequences list and the cart pages describe the audience by its label.

Carts take the first sequence in the list that matches them, so drag the wholesale sequence above any broader sequence that would otherwise take those carts first, as [Sequences](../feature-tour/sequences.md) explains. Then use **Try It With a Real Cart** with one of a wholesale customer's incomplete carts. Abandoned Cart reports whether the cart matches, and warns you if a sequence higher in the list would take it first.

## Offer the Audience in a Preset

A preset can start new sequences with your audience already chosen. Set its `audienceType` to your type handle in a [`registerPresets`](events.md#the-registerpresets-event) listener:

```php
use verbb\abandonedcart\events\RegisterPresetsEvent;
use verbb\abandonedcart\models\Preset;
use verbb\abandonedcart\services\Presets;
use yii\base\Event;

Event::on(Presets::class, Presets::EVENT_REGISTER_PRESETS, function(RegisterPresetsEvent $event) {
    $event->presets[] = new Preset([
        'handle' => 'wholesaleFollowUp',
        'name' => 'Wholesale Follow-Up',
        'description' => 'One reminder for trade accounts, a day after they leave a cart.',
        'audienceType' => 'wholesale',
        'emails' => [
            [
                'delayMinutes' => 1440,
                'subject' => 'Your order is ready when you are',
                'heading' => 'Your cart is saved',
                'message' => 'Your account pricing still applies.',
            ],
        ],
    ]);
});
```

The preset only validates while your `registerAudiences` listener is registered. Without it, Abandoned Cart leaves the preset out and logs why.

## When the Listener Is Removed

If you remove the module, or the listener stops running, sequences that use the audience stay as they are, but they match no carts, so those carts carry on to the next sequence in the list. The sequences list shows the type handle in place of the label, and the sequence can't be saved again until you choose another audience.
