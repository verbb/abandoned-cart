<?php
namespace verbb\abandonedcart\queue\jobs;

use Craft;
use craft\queue\BaseJob;

/**
 * Lets 4.x reminder jobs still in the queue after the upgrade finish quietly. The upgrade moves those carts into
 * Everyone Else, which sends their remaining email itself.
 *
 * @deprecated in 5.0.0
 */
class SendEmailReminder extends BaseJob
{
    // Properties
    // =========================================================================

    public ?int $cartId = null;
    public ?int $reminder = null;


    // Public Methods
    // =========================================================================

    public function execute($queue): void
    {
        Craft::info('Skipped a 4.x reminder job left in the queue; the 5.0 scheduler sends recovery emails now.', __METHOD__);
    }


    // Protected Methods
    // =========================================================================

    protected function defaultDescription(): ?string
    {
        return Craft::t('abandoned-cart', 'Clearing a 4.x reminder job');
    }
}
