<?php
namespace verbb\abandonedcart\events;

use verbb\abandonedcart\models\Customer;

use yii\base\Event;

use craft\commerce\elements\Order;

class ConsentEvent extends Event
{
    // Properties
    // =========================================================================

    public Order $order;
    public Customer $customer;
    public bool $hasConsent = true;
}
