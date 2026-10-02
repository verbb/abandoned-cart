<?php
namespace verbb\abandonedcart\queue\jobs;

use verbb\abandonedcart\AbandonedCart;

use Craft;
use craft\queue\BaseJob;

class SendEmailReminder extends BaseJob
{
    // Properties
    // =========================================================================

    public int $cartId;
    public int $reminder;


    // Public Methods
    // =========================================================================

    public function execute($queue): void
    {
        $carts = AbandonedCart::$plugin->getCarts();
        $cart = $carts->claimReminderForSending($this->cartId, $this->reminder);

        if (!$cart) {
            $this->setProgress($queue, 1);

            return;
        }

        $settings = AbandonedCart::$plugin->getSettings();

        if ($this->reminder === 1) {
            $carts->sendMail(
                $cart,
                $settings->getFirstReminderSubject(),
                $cart->email,
                $settings->getFirstReminderTemplate(),
            );
        } elseif (!$settings->getDisableSecondReminder()) {
            $carts->sendMail(
                $cart,
                $settings->getSecondReminderSubject(),
                $cart->email,
                $settings->getSecondReminderTemplate(),
            );
        }

        $this->setProgress($queue, 1);
    }


    // Protected Methods
    // =========================================================================

    protected function defaultDescription(): ?string
    {
        return Craft::t('abandoned-cart', 'Send abandoned cart reminder');
    }
}
