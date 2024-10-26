<?php
namespace verbb\abandonedcart\events;

use craft\events\CancelableEvent;
use craft\mail\Message;

use craft\commerce\elements\Order;

class BeforeMailSend extends CancelableEvent
{
    // Properties
    // =========================================================================

    public Order $order;
    public Message $message;
}
