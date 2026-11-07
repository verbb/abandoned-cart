<?php
namespace verbb\abandonedcart\queue\jobs;

use verbb\abandonedcart\AbandonedCart;
use verbb\abandonedcart\services\Runs;

use Craft;
use craft\queue\BaseJob;

class ProcessRecoveries extends BaseJob
{
    // Public Methods
    // =========================================================================

    public function execute($queue): void
    {
        AbandonedCart::$plugin->getRecoveries()->run(null, Runs::TRIGGER_QUEUE);
    }


    // Protected Methods
    // =========================================================================

    protected function defaultDescription(): ?string
    {
        return Craft::t('abandoned-cart', 'Sending abandoned cart emails');
    }
}
