<?php
namespace verbb\abandonedcart\controllers;

use verbb\abandonedcart\AbandonedCart;
use verbb\abandonedcart\helpers\Duration;

use Craft;
use craft\fields\Lightswitch;

use yii\web\Response;

class SettingsController extends BaseAdminController
{
    // Public Methods
    // =========================================================================

    public function actionIndex(): Response
    {
        $settings = Craft::$app->getUrlManager()->getRouteParams()['settings'] ?? AbandonedCart::$plugin->getSettings();

        $consentFields = array_values(array_filter(Craft::$app->getFields()->getAllFields(), static fn($field): bool => $field instanceof Lightswitch));

        return $this->renderTemplate('abandoned-cart/settings/index', [
            'settings' => $settings,
            'consentFieldOptions' => [
                ['label' => Craft::t('app', 'None'), 'value' => ''],
                ...array_map(static fn(Lightswitch $field): array => ['label' => $field->name, 'value' => $field->handle], $consentFields),
            ],
        ]);
    }

    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $plugin = AbandonedCart::$plugin;
        $posted = $this->request->getBodyParam('settings', []);

        foreach (['abandonedAfterMinutes', 'recoveryWindowMinutes', 'attributionWindowMinutes', 'contactLimitMinutes'] as $key) {
            if (isset($posted[$key])) {
                $posted[$key] = Duration::toMinutes($posted[$key]) ?? 0;
            }
        }

        foreach (['returningCustomersOnly', 'deliveryPaused'] as $key) {
            if (isset($posted[$key])) {
                $posted[$key] = (bool)$posted[$key];
            }
        }

        $settings = $plugin->getSettings();
        $settings->setAttributes($posted, false);

        if (!$settings->validate()) {
            return $this->asModelFailure($settings, Craft::t('abandoned-cart', 'Couldn’t save settings.'), 'settings');
        }

        $stored = Craft::$app->getPlugins()->getStoredPluginInfo('abandoned-cart')['settings'] ?? [];
        $attributes = array_intersect_key($settings->toArray(), array_flip(array_keys($posted)));

        if (!Craft::$app->getPlugins()->savePluginSettings($plugin, array_replace($stored, $attributes))) {
            return $this->asModelFailure($settings, Craft::t('abandoned-cart', 'Couldn’t save settings.'), 'settings');
        }

        return $this->asSuccess(Craft::t('abandoned-cart', 'Settings saved.'));
    }
}
