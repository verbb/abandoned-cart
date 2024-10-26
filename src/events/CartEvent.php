<?php
namespace verbb\abandonedcart\events;

use verbb\abandonedcart\models\Cart;

use yii\base\Event;

class CartEvent extends Event
{
    // Properties
    // =========================================================================

    public Cart $cart;
    public bool $isNew = false;

}
