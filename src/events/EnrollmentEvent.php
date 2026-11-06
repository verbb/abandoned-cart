<?php
namespace verbb\abandonedcart\events;

use verbb\abandonedcart\models\Customer;
use verbb\abandonedcart\models\Sequence;

use craft\events\CancelableEvent;

use craft\commerce\elements\Order;

class EnrollmentEvent extends CancelableEvent
{
    // Properties
    // =========================================================================

    public Order $order;
    public Customer $customer;
    public ?Sequence $sequence = null;

    // Set when cancelling, so the cart shows in Skipped with a reason.
    public ?string $skipReason = null;
}
