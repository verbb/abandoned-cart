<?php
namespace verbb\abandonedcart\migrations;

use craft\db\Migration;

class m261002_000000_recipient_controls extends Migration
{
    // Public Methods
    // =========================================================================

    public function safeUp(): bool
    {
        $this->addColumn('{{%abandonedcart_carts}}', 'recipientKey', $this->string()->after('email'));
        $this->createIndex('abandonedcart_carts_recipientKey_unq', '{{%abandonedcart_carts}}', ['recipientKey'], true);

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropIndex('abandonedcart_carts_recipientKey_unq', '{{%abandonedcart_carts}}');
        $this->dropColumn('{{%abandonedcart_carts}}', 'recipientKey');

        return true;
    }
}
