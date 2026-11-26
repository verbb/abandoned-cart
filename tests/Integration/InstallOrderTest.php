<?php

use verbb\abandonedcart\db\Table;
use verbb\abandonedcart\migrations\Install;

use craft\events\PluginEvent;
use craft\helpers\Db;
use craft\services\Plugins;

use craft\commerce\db\Table as CommerceTable;
use craft\commerce\Plugin as Commerce;

// Drops and re-adds foreign keys, which MySQL commits immediately.
uses(verbb\abandonedcart\tests\Integration\UpgradeTestCase::class);

it('adds the Commerce order keys once Commerce installs after the plugin', function() {
    $db = Craft::$app->getDb();
    $orders = $db->getSchema()->getRawTableName(CommerceTable::ORDERS);
    $columns = [
        [Table::RECOVERIES, 'orderId'],
        [Table::RECOVERIES, 'recoveredOrderId'],
        [Table::SKIPPED_CARTS, 'orderId'],
    ];
    $orderKeys = function(string $table, string $column) use ($db, $orders): int {
        $keys = $db->getSchema()->getTableSchema($table, true)->foreignKeys;

        return count(array_filter($keys, fn($key) => $key[0] === $orders && isset($key[$column])));
    };

    // A fresh install from project config installs the plugin before Commerce, so these keys can't be added yet.
    $migration = new Install(['compact' => true]);

    foreach ($columns as [$table, $column]) {
        $migration->dropForeignKey(Db::findForeignKey($table, $column), $table);

        expect($orderKeys($table, $column))->toBe(0);
    }

    // Twice, to show that a key already there isn't added again.
    foreach ([1, 2] as $install) {
        Craft::$app->getPlugins()->trigger(Plugins::EVENT_AFTER_INSTALL_PLUGIN, new PluginEvent([
            'plugin' => Commerce::getInstance(),
        ]));
    }

    foreach ($columns as [$table, $column]) {
        expect($orderKeys($table, $column))->toBe(1, "{$table}.{$column} should reference Commerce orders once.");
    }
});
