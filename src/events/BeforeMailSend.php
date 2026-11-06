<?php
namespace verbb\abandonedcart\events;

use craft\events\CancelableEvent;
use craft\mail\Message;

use craft\commerce\elements\Order;

/**
 * @deprecated in 5.0.0. Use `SendEvent` with `Attempts::EVENT_BEFORE_SEND`.
 */
class BeforeMailSend extends CancelableEvent
{
    // Properties
    // =========================================================================

    public Order $order;
    public Message $message;
}
