<?php
namespace verbb\abandonedcart\base;

use verbb\abandonedcart\AbandonedCart;
use verbb\abandonedcart\services\Carts;
use verbb\abandonedcart\services\Service;

use Craft;

use verbb\base\LogTrait;
use verbb\base\helpers\Plugin;

trait PluginTrait
{
    // Static Properties
    // =========================================================================

    public static ?AbandonedCart $plugin = null;

    // Traits
    // =========================================================================

    use LogTrait;
    

    // Static Methods
    // =========================================================================

    public static function config(): array
    {
        Plugin::bootstrapPlugin('abandoned-cart');

        return [
            'components' => [
                'carts' => Carts::class,
                'service' => Service::class,
            ],
        ];
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

}