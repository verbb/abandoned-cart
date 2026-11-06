<?php
namespace verbb\abandonedcart\events;

use verbb\abandonedcart\models\CartSummary;

use yii\base\Event;

use craft\commerce\elements\Order;

class ResolveLanguageEvent extends Event
{
    // Properties
    // =========================================================================

    public CartSummary $cart;
    public ?Order $order = null;
    public string $language;
}
