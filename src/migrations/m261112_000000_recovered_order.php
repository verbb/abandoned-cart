<?php
namespace verbb\abandonedcart\migrations;

use verbb\abandonedcart\db\Table;

use craft\db\Migration;

use yii\db\Expression;

use craft\commerce\db\Table as CommerceTable;

/**
 * Records which order recovered a cart, since a customer can complete a different cart from the one they left.
 */
class m261112_000000_recovered_order extends Migration
{
    // Public Methods
    // =========================================================================

    public function safeUp(): bool
    {
        if (!$this->db->columnExists(Table::RECOVERIES, 'recoveredOrderId')) {
            $this->addColumn(Table::RECOVERIES, 'recoveredOrderId', $this->integer()->after('recoveredAt'));
            $this->addForeignKey(null, Table::RECOVERIES, ['recoveredOrderId'], CommerceTable::ORDERS, ['id'], 'SET NULL', null);
        }

        // Until now, only the recovery's own cart could recover it.
        $this->update(Table::RECOVERIES, ['recoveredOrderId' => new Expression('[[orderId]]')], ['status' => 'recovered', 'recoveredOrderId' => null], [], false);

        return true;
    }

    public function safeDown(): bool
    {
        echo "m261112_000000_recovered_order cannot be reverted.\n";

        return false;
    }
}
