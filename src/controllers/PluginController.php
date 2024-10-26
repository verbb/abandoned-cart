<?php
namespace verbb\abandonedcart\controllers;

use verbb\abandonedcart\AbandonedCart;
use verbb\abandonedcart\models\Settings;

use Craft;
use craft\helpers\UrlHelper;
use craft\web\Controller;

use yii\web\Response;

class PluginController extends Controller
{
    // Public Methods
    // =========================================================================

    public function actionSettings(): Response
    {
        /* @var Settings $settings */
        $settings = AbandonedCart::$plugin->getSettings();

        return $this->renderTemplate('abandoned-cart/settings', [
            'settings' => $settings,
        ]);
    }
}
