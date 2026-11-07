<?php
namespace verbb\abandonedcart;

use verbb\abandonedcart\base\PluginTrait;
use verbb\abandonedcart\controllers\UsersController as AbandonedCartUsersController;
use verbb\abandonedcart\models\Settings;
use verbb\abandonedcart\services\EmailTemplates;
use verbb\abandonedcart\services\Presets;
use verbb\abandonedcart\variables\AbandonedCartVariable;

use Craft;
use craft\controllers\UsersController;
use craft\events\DefineEditUserScreensEvent;
use craft\base\Plugin;
use craft\console\Application as ConsoleApplication;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\helpers\UrlHelper;
use craft\services\ProjectConfig;
use craft\services\UserPermissions;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;

use yii\base\Event;

class AbandonedCart extends Plugin
{
    // Constants
    // =========================================================================

    public const PERMISSION_MANAGE_SEQUENCES = 'abandonedCart-manageSequences';
    public const PERMISSION_MANAGE_DO_NOT_EMAIL = 'abandonedCart-manageDoNotEmail';
    public const PERMISSION_VIEW_SYSTEM_STATUS = 'abandonedCart-viewSystemStatus';


    // Properties
    // =========================================================================

    public bool $hasCpSection = true;
    public bool $hasCpSettings = true;
    public string $schemaVersion = '5.0.2';
    public string $minVersionRequired = '3.0.0';


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
        $this->_registerVariables();
        $this->_registerPermissions();
        $this->_registerSiteRoutes();
        $this->_registerEventHandlers();

        if (Craft::$app->getRequest()->getIsCpRequest()) {
            $this->_registerCpRoutes();
            $this->_registerUserScreen();
        }

        if (Craft::$app instanceof ConsoleApplication) {
            $this->controllerNamespace = 'verbb\\abandonedcart\\console\\controllers';
        }
    }

    public function getPluginName(): string
    {
        return Craft::t('abandoned-cart', $this->getSettings()->pluginName);
    }

    public function getCpNavItem(): array
    {
        $nav = parent::getCpNavItem();
        $user = Craft::$app->getUser();

        $nav['label'] = $this->getPluginName();
        $nav['url'] = 'abandoned-cart';

        if ($user->checkPermission('accessPlugin-abandoned-cart')) {
            $nav['subnav']['overview'] = ['label' => Craft::t('abandoned-cart', 'Overview'), 'url' => 'abandoned-cart'];
            $nav['subnav']['carts'] = ['label' => Craft::t('abandoned-cart', 'Carts'), 'url' => 'abandoned-cart/carts'];
            $nav['subnav']['customers'] = ['label' => Craft::t('abandoned-cart', 'Customers'), 'url' => 'abandoned-cart/customers'];
            $nav['subnav']['sequences'] = ['label' => Craft::t('abandoned-cart', 'Sequences'), 'url' => 'abandoned-cart/sequences'];
        }

        if ($user->getIsAdmin() || $user->checkPermission(self::PERMISSION_VIEW_SYSTEM_STATUS)) {
            $nav['subnav']['settings'] = [
                'label' => Craft::t('abandoned-cart', 'Settings'),
                'url' => $user->getIsAdmin() ? 'abandoned-cart/settings' : 'abandoned-cart/settings/system-status',
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

    private function _registerProjectConfigEventListeners(): void
    {
        $emailTemplates = $this->getEmailTemplates();

        Craft::$app->getProjectConfig()
            ->onAdd(EmailTemplates::CONFIG_KEY . '.{uid}', [$emailTemplates, 'handleChangedTemplate'])
            ->onUpdate(EmailTemplates::CONFIG_KEY . '.{uid}', [$emailTemplates, 'handleChangedTemplate'])
            ->onRemove(EmailTemplates::CONFIG_KEY . '.{uid}', [$emailTemplates, 'handleDeletedTemplate']);

        Event::on(ProjectConfig::class, ProjectConfig::EVENT_REBUILD, [$emailTemplates, 'rebuildProjectConfig']);

        $presets = $this->getPresets();

        Craft::$app->getProjectConfig()
            ->onAdd(Presets::CONFIG_KEY . '.{uid}', [$presets, 'handleChangedPreset'])
            ->onUpdate(Presets::CONFIG_KEY . '.{uid}', [$presets, 'handleChangedPreset'])
            ->onRemove(Presets::CONFIG_KEY . '.{uid}', [$presets, 'handleChangedPreset']);

        Event::on(ProjectConfig::class, ProjectConfig::EVENT_REBUILD, [$presets, 'rebuildProjectConfig']);
    }

    private function _registerVariables(): void
    {
        Event::on(CraftVariable::class, CraftVariable::EVENT_INIT, function(Event $event) {
            $event->sender->set('abandonedCart', AbandonedCartVariable::class);
        });
    }

    private function _registerPermissions(): void
    {
        Event::on(UserPermissions::class, UserPermissions::EVENT_REGISTER_PERMISSIONS, function(RegisterUserPermissionsEvent $event) {
            $event->permissions[] = [
                'heading' => $this->getPluginName(),
                'permissions' => [
                    self::PERMISSION_MANAGE_SEQUENCES => ['label' => Craft::t('abandoned-cart', 'Manage sequences and send emails')],
                    self::PERMISSION_MANAGE_DO_NOT_EMAIL => ['label' => Craft::t('abandoned-cart', 'Manage Do Not Email')],
                    self::PERMISSION_VIEW_SYSTEM_STATUS => ['label' => Craft::t('abandoned-cart', 'View System Status')],
                ],
            ];
        });
    }

    private function _registerCpRoutes(): void
    {
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, function(RegisterUrlRulesEvent $event) {
            $event->rules['abandoned-cart'] = 'abandoned-cart/overview/index';
            $event->rules['abandoned-cart/carts'] = 'abandoned-cart/carts/index';
            $event->rules['abandoned-cart/carts/<recoveryId:\d+>'] = 'abandoned-cart/carts/view';
            $event->rules['abandoned-cart/carts/skipped/<skippedCartId:\d+>'] = 'abandoned-cart/carts/view-skipped';
            $event->rules['abandoned-cart/customers'] = 'abandoned-cart/customers/index';
            $event->rules['abandoned-cart/customers/<customerId:\d+>'] = 'abandoned-cart/customers/view';
            $event->rules['abandoned-cart/sequences'] = 'abandoned-cart/sequences/index';
            $event->rules['abandoned-cart/sequences/new'] = 'abandoned-cart/sequences/edit';
            $event->rules['abandoned-cart/sequences/<sequenceId:\d+>'] = 'abandoned-cart/sequences/edit';
            $event->rules['abandoned-cart/settings'] = 'abandoned-cart/settings/index';
            $event->rules['abandoned-cart/settings/email-templates'] = 'abandoned-cart/email-templates/index';
            $event->rules['abandoned-cart/settings/email-templates/new'] = 'abandoned-cart/email-templates/edit';
            $event->rules['abandoned-cart/settings/email-templates/<templateId:\d+>'] = 'abandoned-cart/email-templates/edit';
            $event->rules['abandoned-cart/settings/presets'] = 'abandoned-cart/presets/index';
            $event->rules['abandoned-cart/settings/presets/new'] = 'abandoned-cart/presets/edit';
            $event->rules['abandoned-cart/settings/presets/<uid:{uid}>'] = 'abandoned-cart/presets/edit';
            $event->rules['abandoned-cart/settings/system-status'] = 'abandoned-cart/system-status/index';
            $event->rules['myaccount/abandoned-cart'] = 'abandoned-cart/users/index';
            $event->rules['users/<userId:\d+>/abandoned-cart'] = 'abandoned-cart/users/index';
        });
    }

    private function _registerUserScreen(): void
    {
        Event::on(UsersController::class, UsersController::EVENT_DEFINE_EDIT_SCREENS, function(DefineEditUserScreensEvent $event) {
            if ($event->currentUser?->can('accessPlugin-abandoned-cart')) {
                $event->screens[AbandonedCartUsersController::SCREEN] = ['label' => $this->getPluginName()];
            }
        });
    }

    private function _registerSiteRoutes(): void
    {
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_SITE_URL_RULES, function(RegisterUrlRulesEvent $event) {
            $event->rules['abandoned-cart/recover/<token:[A-Za-z0-9_-]+={0,2}>'] = 'abandoned-cart/recovery/restore';
            $event->rules['abandoned-cart/unsubscribe/<token:[A-Za-z0-9_-]+={0,2}>'] = 'abandoned-cart/recovery/unsubscribe';

            // Links in emails sent by 4.x.
            $event->rules['abandoned-cart-restore'] = 'abandoned-cart/recovery/restore-legacy';
        });
    }

    private function _registerEventHandlers(): void
    {
        // The engine's Commerce and garbage-collection listeners are registered by the Recoveries service.
        if (Craft::$app->getPlugins()->isPluginInstalled('abandoned-cart')) {
            $this->getRecoveries()->registerEventHandlers();
        }
    }
}
