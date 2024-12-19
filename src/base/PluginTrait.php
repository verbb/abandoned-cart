<?php
namespace verbb\abandonedcart\base;

use verbb\abandonedcart\AbandonedCart;
use verbb\abandonedcart\services\Carts;
use verbb\abandonedcart\services\Service;

use Craft;

use yii\log\Logger;

use verbb\base\BaseHelper;

trait PluginTrait
{
    // Static Properties
    // =========================================================================

    public static AbandonedCart $plugin;


    // Public Methods
    // =========================================================================

    public static function log(string $message, array $attributes = []): void
    {
        if ($attributes) {
            $message = Craft::t('abandoned-cart', $message, $attributes);
        }

        Craft::getLogger()->log($message, Logger::LEVEL_INFO, 'abandoned-cart');
    }

    public static function error(string $message, array $attributes = []): void
    {
        if ($attributes) {
            $message = Craft::t('abandoned-cart', $message, $attributes);
        }

        Craft::getLogger()->log($message, Logger::LEVEL_ERROR, 'abandoned-cart');
    }


    // Public Methods
    // =========================================================================

    public function getCarts(): Carts
    {
        return $this->get('carts');
    }

    public function getService(): Service
    {
        return $this->get('service');
    }


    // Private Methods
    // =========================================================================

    private function _setPluginComponents(): void
    {
        $this->setComponents([
            'carts' => Carts::class,
            'service' => Service::class,
        ]);

        BaseHelper::registerModule();
    }

    private function _setLogging(): void
    {
        BaseHelper::setFileLogging('abandoned-cart');
    }

}