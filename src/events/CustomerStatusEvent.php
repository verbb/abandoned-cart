<?php
namespace verbb\abandonedcart\events;

use verbb\abandonedcart\models\Customer;

use craft\events\CancelableEvent;

class CustomerStatusEvent extends CancelableEvent
{
    // Properties
    // =========================================================================

    public Customer $customer;
    public string $status;
    public ?string $source = null;
    public ?string $note = null;
}
