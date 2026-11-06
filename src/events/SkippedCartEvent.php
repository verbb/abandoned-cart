<?php
namespace verbb\abandonedcart\events;

use verbb\abandonedcart\models\SkippedCart;

use yii\base\Event;

class SkippedCartEvent extends Event
{
    // Properties
    // =========================================================================

    public SkippedCart $skippedCart;
}
