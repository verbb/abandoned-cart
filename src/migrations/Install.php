<?php
namespace verbb\abandonedcart\migrations;

use verbb\abandonedcart\AbandonedCart;

use Craft;
use craft\db\Migration;
use craft\helpers\MigrationHelper;

class Install extends Migration
{
    // Public Methods
    // =========================================================================

    public function safeUp(): bool
    {
        $this->createTables();
        $this->createIndexes();
        $this->createForeignKeys();

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropForeignKeys();
        $this->dropTables();

        return true;
    }

    public function createTables(): void
    {
        $this->archiveTableIfExists('{{%abandonedcart_carts}}');
        $this->createTable('{{%abandonedcart_carts}}', [
            'id' => $this->primaryKey(),
            'orderId' => $this->integer()->notNull(),
            'email' => $this->string()->notNull()->defaultValue(''),
            'clicked' => $this->boolean()->defaultValue(false),
            'isScheduled' => $this->boolean()->defaultValue(false),
            'firstReminder' => $this->boolean()->defaultValue(false),
            'secondReminder' => $this->boolean()->defaultValue(false),
            'isRecovered' => $this->boolean()->defaultValue(false),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);
    }

    public function createIndexes(): void
    {
        $this->createIndex(null, '{{%abandonedcart_carts}}', ['orderId'], false);
    }

    public function createForeignKeys(): void
    {
        if ($this->db->tableExists('{{%commerce_orders}}')) {
            $this->addForeignKey(null, '{{%abandonedcart_carts}}', ['orderId'], '{{%commerce_orders}}', ['id'], 'CASCADE', null);
        }
    }

    public function dropTables(): void
    {
        $this->dropTableIfExists('{{%abandonedcart_carts}}');
    }

    public function dropForeignKeys(): void
    {
        if ($this->db->tableExists('{{%abandonedcart_carts}}')) {
            MigrationHelper::dropAllForeignKeysOnTable('{{%abandonedcart_carts}}', $this);
        }
    }
}
