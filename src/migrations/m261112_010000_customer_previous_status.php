<?php
namespace verbb\abandonedcart\migrations;

use verbb\abandonedcart\db\Table;

use craft\db\Migration;

/**
 * Remembers a customer's status before they were added to Do Not Email, so removing them restores it.
 */
class m261112_010000_customer_previous_status extends Migration
{
    // Public Methods
    // =========================================================================

    public function safeUp(): bool
    {
        if (!$this->db->columnExists(Table::CUSTOMERS, 'previousStatus')) {
            $this->addColumn(Table::CUSTOMERS, 'previousStatus', $this->text()->after('statusChangedAt'));
        }

        return true;
    }

    public function safeDown(): bool
    {
        echo "m261112_010000_customer_previous_status cannot be reverted.\n";

        return false;
    }
}
