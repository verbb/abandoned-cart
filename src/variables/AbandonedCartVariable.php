<?php
namespace verbb\abandonedcart\variables;

use verbb\abandonedcart\AbandonedCart;
use verbb\abandonedcart\models\Customer;
use verbb\abandonedcart\models\Settings;

use Craft;

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

    /**
     * The signed-in customer's Abandoned Cart record, for account pages that let them manage reminder emails.
     */
    public function getCurrentCustomer(): ?Customer
    {
        $user = Craft::$app->getUser()->getIdentity();

        return $user ? AbandonedCart::$plugin->getCustomers()->getCustomerByUser($user) : null;
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
