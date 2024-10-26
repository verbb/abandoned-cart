<?php
namespace verbb\abandonedcart\variables;

use verbb\abandonedcart\AbandonedCart;

class AbandonedCartVariable
{
    // Public Methods
    // =========================================================================

    public function getPlugin(): AbandonedCart
    {
        return AbandonedCart::$plugin;
    }

    public function getPluginName(): string
    {
        return AbandonedCart::$plugin->getPluginName();
    }
    
}