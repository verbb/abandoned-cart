<?php
namespace verbb\abandonedcart\migrations;

use verbb\abandonedcart\db\Table;

use Craft;
use craft\db\Migration;

/**
 * The default sequence is now called All Other Carts. A store that has renamed it keeps its own name.
 */
class m261107_010000_rename_default_sequence extends Migration
{
    // Public Methods
    // =========================================================================

    public function safeUp(): bool
    {
        $this->update(Table::SEQUENCES, ['name' => Craft::t('abandoned-cart', 'All Other Carts')], [
            'isFallback' => true,
            'name' => array_unique(['Everyone Else', Craft::t('abandoned-cart', 'Everyone Else')]),
        ]);

        return true;
    }

    public function safeDown(): bool
    {
        echo "m261107_010000_rename_default_sequence cannot be reverted.\n";

        return false;
    }
}
