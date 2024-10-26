# Events
Abandoned Cart provides a collection of events for extending its functionality. Modules and plugins can register event listeners, typically in their `init()` methods, to modify Abandoned Cart’s behavior.

## Cart Events

### The `beforeSaveCart` event
The event that is triggered before a cart is saved.

```php
use verbb\abandonedcart\events\CartEvent;
use verbb\abandonedcart\services\Carts;
use yii\base\Event;

Event::on(Carts::class, Carts::EVENT_BEFORE_SAVE_CART, function(CartEvent $event) {
    $cart = $event->cart;
    $isNew = $event->isNew;
    // ...
});
```

### The `afterSaveCart` event
The event that is triggered after a cart is saved.

```php
use verbb\abandonedcart\events\CartEvent;
use verbb\abandonedcart\services\Carts;
use yii\base\Event;

Event::on(Carts::class, Carts::EVENT_AFTER_SAVE_CART, function(CartEvent $event) {
    $cart = $event->cart;
    $isNew = $event->isNew;
    // ...
});
```

### The `beforeMailSend` event
The event that is triggered before an email is sent.

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
