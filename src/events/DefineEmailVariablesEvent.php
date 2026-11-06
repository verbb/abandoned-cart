<?php
namespace verbb\abandonedcart\events;

use verbb\abandonedcart\models\CartSummary;
use verbb\abandonedcart\models\Email;

use yii\base\Event;

use craft\commerce\elements\Order;

class DefineEmailVariablesEvent extends Event
{
    // Properties
    // =========================================================================

    public Email $email;
    public CartSummary $cart;
    public ?Order $order = null;
    public array $variables = [];
}
