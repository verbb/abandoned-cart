<?php
namespace verbb\abandonedcart\migrations;

use verbb\abandonedcart\AbandonedCart;
use verbb\abandonedcart\services\Presets;

use Craft;
use craft\db\Migration;

/**
 * Sequence presets move from code to project config, so they can be managed under Settings. Existing installs get
 * the built-in presets.
 */
class m261107_000000_sequence_presets extends Migration
{
    // Public Methods
    // =========================================================================

    public function safeUp(): bool
    {
        $projectConfig = Craft::$app->getProjectConfig();

        // Where the incoming project config already has this version, it brings the presets with it.
        if (version_compare((string)$projectConfig->get('plugins.abandoned-cart.schemaVersion', true), '5.0.1', '>=')) {
            $projectConfig->processConfigChanges(Presets::CONFIG_KEY);

            return true;
        }

        AbandonedCart::$plugin->getPresets()->installBuiltInPresets();

        return true;
    }

    public function safeDown(): bool
    {
        echo "m261107_000000_sequence_presets cannot be reverted.\n";

        return false;
    }
}
