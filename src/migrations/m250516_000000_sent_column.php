<?php
namespace verbb\abandonedcart\migrations;

use craft\db\Migration;

class m250516_000000_sent_column extends Migration
{
    // Public Methods
    // =========================================================================

    public function safeUp(): bool
    {
        if (!$this->db->columnExists('{{%abandonedcart_carts}}', 'isSent')) {
            $this->addColumn('{{%abandonedcart_carts}}', 'isSent', $this->boolean()->defaultValue(false)->after('isRecovered'));
        }

        return true;
    }

    public function safeDown(): bool
    {
        echo "m250516_000000_sent_column cannot be reverted.\n";

        return false;
    }
}
