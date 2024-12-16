# Configuration
Create a `abandoned-cart.php` file under your `/config` directory with the following options available to you. You can also use multi-environment options to change these per environment.

The below shows the defaults already used by Abandoned Cart, so you don't need to add these options unless you want to modify the values.

```php
<?php

return [
    '*' => [
        'pluginName' => 'Abandoned Cart',
        'passKey' => null,
        'restoreExpiryHours' => 48,
        'firstReminderDelay' => 1,
        'secondReminderDelay' => 12,
        'discountCode' => null,
        'firstReminderTemplate' => 'abandoned-cart/emails/first',
        'secondReminderTemplate' => 'abandoned-cart/emails/second',
        'firstReminderSubject' => 'You‘ve left some items in your cart',
        'secondReminderSubject' => 'Your items are still waiting - don‘t miss out',
        'recoveryUrl' => 'shop/cart',
        'disableSecondReminder' => false,
        'blacklist' => null,
    ],
];
```

## Configuration options
- `pluginName` - If you wish to customise the plugin name.
- `passKey` - A generated, unique string to increase security against crons being run inadvertently.
- `restoreExpiryHours` - How many hours should abandoned cart restore links last for after being sent.
- `firstReminderDelay` - How many hours after a cart has been abandoned should the 1st reminder be sent.
- `secondReminderDelay` - How many hours after a cart has been abandoned should the 2nd reminder be sent.
- `discountCode` - Enter the discount code that abandoned carts can use.
- `firstReminderTemplate` - Use a custom template for the 1st reminder email.
- `secondReminderTemplate` - Use a custom template for the 2nd reminder email.
- `firstReminderSubject` - The subject for the 1st reminder email.
- `secondReminderSubject` - The subject for the 2nd reminder email.
- `recoveryUrl` - By default recovered carts will be redirected to shop/cart, use this field if you use something different.
- `disableSecondReminder` - If disabled only the 1st reminder will be sent.
- `blacklist` - Enter emails seperated by a comma that should be ignored.


## Control Panel
You can also manage configuration settings through the Control Panel by visiting Settings → Abandoned Cart.
