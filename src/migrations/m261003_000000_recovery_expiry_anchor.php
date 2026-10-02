<?php
namespace verbb\abandonedcart\migrations;

use craft\db\Migration;

use yii\db\Expression;

class m261003_000000_recovery_expiry_anchor extends Migration
{
    // Public Methods
    // =========================================================================

    public function safeUp(): bool
    {
        $this->addColumn('{{%abandonedcart_carts}}', 'dateLastSent', $this->dateTime()->after('isSent'));

        // Preserve the effective expiry of recovery links that were issued before this field existed.
        $this->update(
            '{{%abandonedcart_carts}}',
            ['dateLastSent' => new Expression('[[dateUpdated]]')],
            [
                'or',
                ['isSent' => true],
                ['firstReminder' => true],
                ['secondReminder' => true],
            ],
            [],
            false,
        );

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropColumn('{{%abandonedcart_carts}}', 'dateLastSent');

        return true;
    }
}
