<?php
namespace verbb\abandonedcart\events;

use verbb\abandonedcart\models\Sequence;

use yii\base\Event;

use craft\commerce\elements\Order;

class MatchAudienceEvent extends Event
{
    // Properties
    // =========================================================================

    public Sequence $sequence;
    public Order $order;
    public ?bool $matches = null;
}
