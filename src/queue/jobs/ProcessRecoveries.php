<?php
namespace verbb\abandonedcart\queue\jobs;

use verbb\abandonedcart\AbandonedCart;

use Craft;
use craft\queue\BaseJob;

class ProcessRecoveries extends BaseJob
{
    // Public Methods
    // =========================================================================

    public function execute($queue): void
    {
        AbandonedCart::$plugin->getRecoveries()->run();
    }


    // Protected Methods
    // =========================================================================

    protected function defaultDescription(): ?string
    {
        return Craft::t('abandoned-cart', 'Sending abandoned cart emails');
    }
}
