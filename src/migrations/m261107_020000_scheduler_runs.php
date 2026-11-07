<?php
namespace verbb\abandonedcart\migrations;

use verbb\abandonedcart\db\Table;

use craft\db\Migration;

/**
 * A record of each scheduler run, for System Status.
 */
class m261107_020000_scheduler_runs extends Migration
{
    // Public Methods
    // =========================================================================

    public function safeUp(): bool
    {
        if (!$this->db->tableExists(Table::RUNS)) {
            $install = new Install();
            $install->createRunsTable();
            $this->createIndex(null, Table::RUNS, ['startedAt']);
        }

        return true;
    }

    public function safeDown(): bool
    {
        echo "m261107_020000_scheduler_runs cannot be reverted.\n";

        return false;
    }
}
