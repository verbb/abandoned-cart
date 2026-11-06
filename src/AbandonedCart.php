<?php
namespace verbb\abandonedcart;

use verbb\abandonedcart\base\PluginTrait;
use verbb\abandonedcart\models\Settings;
use verbb\abandonedcart\services\EmailTemplates;

use Craft;
use craft\base\Plugin;
use craft\services\ProjectConfig;

use yii\base\Event;

class AbandonedCart extends Plugin
{
    // Properties
    // =========================================================================

    // The control panel screens arrive with the settings and sequence phases of the 5.0 build.
    public bool $hasCpSection = false;
    public bool $hasCpSettings = false;
    public string $schemaVersion = '5.0.0';


    // Traits
    // =========================================================================

    use PluginTrait;


    // Public Methods
    // =========================================================================

    public function init(): void
    {
        parent::init();

        self::$plugin = $this;

        $this->_registerProjectConfigEventListeners();
    }

    public function getPluginName(): string
    {
        return Craft::t('abandoned-cart', $this->getSettings()->pluginName);
    }


    // Protected Methods
    // =========================================================================

    protected function createSettingsModel(): Settings
    {
        return new Settings();
    }


    // Private Methods
    // =========================================================================

    private function _registerProjectConfigEventListeners(): void
    {
        $emailTemplates = $this->getEmailTemplates();

        Craft::$app->getProjectConfig()
            ->onAdd(EmailTemplates::CONFIG_KEY . '.{uid}', [$emailTemplates, 'handleChangedTemplate'])
            ->onUpdate(EmailTemplates::CONFIG_KEY . '.{uid}', [$emailTemplates, 'handleChangedTemplate'])
            ->onRemove(EmailTemplates::CONFIG_KEY . '.{uid}', [$emailTemplates, 'handleDeletedTemplate']);

        Event::on(ProjectConfig::class, ProjectConfig::EVENT_REBUILD, [$emailTemplates, 'rebuildProjectConfig']);
    }
}
