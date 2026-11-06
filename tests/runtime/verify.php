<?php

require_once __DIR__ . '/bootstrap.php';

$app = require CRAFT_VENDOR_PATH . '/craftcms/cms/bootstrap/console.php';
$app->setEdition(\craft\enums\CmsEdition::Pro);
$plugins = $app->getPlugins();

foreach (['commerce', 'abandoned-cart'] as $handle) {
    $plugin = $plugins->getPlugin($handle);

    if (!$app->getIsInstalled() || !$plugin || !$plugins->isPluginInstalled($handle) || !$plugins->isPluginEnabled($handle) || $plugins->isPluginUpdatePending($plugin)) {
        throw new RuntimeException('Fresh plugin installation is incomplete: ' . $handle);
    }
}

$source = (new ReflectionClass($plugins->getPlugin('abandoned-cart')))->getFileName();

if (!str_starts_with(realpath($source), realpath(dirname(__DIR__, 2) . '/src') . '/')) {
    throw new RuntimeException('Craft loaded a different Abandoned Cart checkout.');
}
