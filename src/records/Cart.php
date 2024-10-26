<?php
namespace verbb\abandonedcart\records;

use craft\db\ActiveRecord;

use yii\db\ActiveQueryInterface;

use craft\commerce\elements\Order;

class Cart extends ActiveRecord
{
    // Static Methods
    // =========================================================================

    public static function tableName(): string
    {
        return '{{%abandonedcart_carts}}';
    }


    // Public Methods
    // =========================================================================

    public function getOrder(): array
    {
        return Order::findAll($this->orderId);
    }
}
