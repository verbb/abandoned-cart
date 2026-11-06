<?php
namespace verbb\abandonedcart\variables;

use verbb\abandonedcart\AbandonedCart;
use verbb\abandonedcart\models\Settings;

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

    public function getSettings(): Settings
    {
        return AbandonedCart::$plugin->getSettings();
    }

    public function getCurrency(): string
    {
        return AbandonedCart::$plugin->getReports()->getCurrency();
    }

    public function getSequences(): array
    {
        return AbandonedCart::$plugin->getSequences()->getAllSequences();
    }

    public function getEmailTemplates(): array
    {
        return AbandonedCart::$plugin->getEmailTemplates()->getAllTemplates();
    }
}
