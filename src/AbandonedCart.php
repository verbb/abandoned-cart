<?php
namespace verbb\abandonedcart;

use verbb\abandonedcart\base\PluginTrait;
use verbb\abandonedcart\models\Settings;
use verbb\abandonedcart\variables\AbandonedCartVariable;

use Craft;
use craft\base\Plugin;
use craft\events\RegisterUrlRulesEvent;
use craft\helpers\UrlHelper;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;

use yii\base\Event;

use craft\commerce\elements\Order;

class AbandonedCart extends Plugin
{
    // Properties
    // =========================================================================

    public bool $hasCpSection = true;
    public bool $hasCpSettings = true;
    public string $schemaVersion = '1.0.0';


    // Traits
    // =========================================================================

    use PluginTrait;


    // Public Methods
    // =========================================================================

    public function init(): void
    {
        parent::init();

        self::$plugin = $this;

        $this->_setPluginComponents();
        $this->_setLogging();
        $this->_registerVariables();
        $this->_registerCraftEventListeners();

        if (Craft::$app->getRequest()->getIsCpRequest()) {
            $this->_registerCpRoutes();
        }

        if (Craft::$app->getRequest()->getIsSiteRequest()) {
            $this->_registerSiteRoutes();
        }
    }

    public function getPluginName(): string
    {
        return Craft::t('abandoned-cart', $this->getSettings()->pluginName);
    }

    public function getCpNavItem(): array
    {
        $nav = parent::getCpNavItem();

        $nav['label'] = $this->getPluginName();
        $nav['url'] = 'abandoned-cart';

        if (Craft::$app->getUser()->checkPermission('accessPlugin-abandoned-cart')) {
            $nav['subnav']['dashboard'] = [
                'label' => Craft::t('abandoned-cart', 'Dashboard'),
                'url' => 'abandoned-cart/dashboard',
            ];
        }

        if (Craft::$app->getUser()->getIsAdmin() && Craft::$app->getConfig()->getGeneral()->allowAdminChanges) {
            $nav['subnav']['settings'] = [
                'label' => Craft::t('abandoned-cart', 'Settings'),
                'url' => 'abandoned-cart/settings',
            ];
        }

        return $nav;
    }

    public function getSettingsResponse(): mixed
    {
        return Craft::$app->getResponse()->redirect(UrlHelper::cpUrl('abandoned-cart/settings'));
    }


    // Protected Methods
    // =========================================================================

    protected function createSettingsModel(): Settings
    {
        return new Settings();
    }


    // Private Methods
    // =========================================================================

    private function _registerCpRoutes(): void
    {
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, function(RegisterUrlRulesEvent $event) {
            $event->rules['abandoned-cart'] = ['template' => 'abandoned-cart/index'];
            $event->rules['abandoned-cart/dashboard'] = 'abandoned-cart/carts/index';
            $event->rules['abandoned-cart/settings'] = 'abandoned-cart/plugin/settings';
        });
    }

    private function _registerSiteRoutes(): void
    {
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_SITE_URL_RULES, function(RegisterUrlRulesEvent $event) {
            $event->rules['abandoned-cart-restore'] = 'abandoned-cart/carts/restore-cart';
        });
    }

    private function _registerVariables(): void
    {
        Event::on(CraftVariable::class, CraftVariable::EVENT_INIT, function(Event $event) {
            $event->sender->set('abandonedCart', AbandonedCartVariable::class);
        });
    }

    private function _registerCraftEventListeners(): void
    {
        Event::on(Order::class, Order::EVENT_AFTER_COMPLETE_ORDER, function(Event $event) {
            $this->getCarts()->markCartAsRecovered($event->sender);
        });
    }
}
