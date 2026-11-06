<?php
namespace verbb\abandonedcart\events;

use verbb\abandonedcart\models\Customer;

use craft\events\CancelableEvent;

class CustomerEvent extends CancelableEvent
{
    // Properties
    // =========================================================================

    public Customer $customer;
    public bool $isNew = false;
}
