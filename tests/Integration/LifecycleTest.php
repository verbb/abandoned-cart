<?php

use craft\db\Query;
use craft\db\Table;

// Schema changes commit implicitly in MySQL, so this file doesn't use the transactional test case.

function abandonedCartTables(): array
{
    $prefix = Craft::$app->getDb()->tablePrefix . 'abandonedcart_';

    return array_values(array_filter(Craft::$app->getDb()->getSchema()->getTableNames(), static fn(string $name): bool => str_starts_with($name, $prefix)));
}

function abandonedCartLeftovers(): array
{
    return [
        'tables' => abandonedCartTables(),
        'elements' => (int)(new Query())->from(Table::ELEMENTS)->where(['like', 'type', 'verbb\\abandonedcart\\%', false])->count(),
        'fields' => (int)(new Query())->from(Table::FIELDS)->where(['like', 'handle', 'abandonedCart%', false])->count(),
        'fieldLayouts' => (int)(new Query())->from(Table::FIELDLAYOUTS)->where(['like', 'type', 'verbb\\abandonedcart\\%', false])->count(),
        'projectConfig' => Craft::$app->getProjectConfig()->get('plugins.abandoned-cart') !== null || Craft::$app->getProjectConfig()->get('abandoned-cart') !== null,
    ];
}

it('installs alongside Commerce', function() {
    expect(Craft::$app->getPlugins()->isPluginInstalled('commerce'))->toBeTrue()
        ->and(Craft::$app->getPlugins()->isPluginInstalled('abandoned-cart'))->toBeTrue()
        ->and(abandonedCartTables())->not->toBeEmpty();
});

it('leaves nothing behind when uninstalled, and installs again cleanly', function() {
    $plugins = Craft::$app->getPlugins();

    try {
        expect($plugins->uninstallPlugin('abandoned-cart'))->toBeTrue()
            ->and(abandonedCartLeftovers())->toBe([
                'tables' => [],
                'elements' => 0,
                'fields' => 0,
                'fieldLayouts' => 0,
                'projectConfig' => false,
            ]);
    } finally {
        if (!$plugins->isPluginInstalled('abandoned-cart')) {
            $plugins->installPlugin('abandoned-cart');
        }
    }

    expect($plugins->isPluginInstalled('abandoned-cart'))->toBeTrue()
        ->and(abandonedCartTables())->not->toBeEmpty();
});
