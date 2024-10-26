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
        $totalSteps = 1;
        
        for ($step = 0; $step < $totalSteps; $step++) { 
            $cart = AbandonedCart::$plugin->getCarts()->getCartById($this->cartId);

            $firstTemplate = AbandonedCart::$plugin->getSettings()->getFirstReminderTemplate();
            $secondTemplate = AbandonedCart::$plugin->getSettings()->getSecondReminderTemplate();
            $firstSubject = AbandonedCart::$plugin->getSettings()->getFirstReminderSubject();
            $secondSubject = AbandonedCart::$plugin->getSettings()->getSecondReminderSubject();
            $secondReminderDisabled = AbandonedCart::$plugin->getSettings()->getDisableSecondReminder();
            
            if ($cart && !$cart->isRecovered) {
                // First Reminder
                if ($this->reminder == 1) {
                    $cart->firstReminder = true;
                    $cart->isScheduled = false;

                    AbandonedCart::$plugin->getCarts()->saveCart($cart);
                    AbandonedCart::$plugin->getCarts()->sendMail($cart, $firstSubject, $cart->email, $firstTemplate);
                }

                // Second Reminder
                if ($this->reminder == 2) {
                    if ($secondReminderDisabled) {
                        $cart->secondReminder = true;
                        $cart->isScheduled = false;

                        AbandonedCart::$plugin->getCarts()->saveCart($cart);
                    } else {
                        $cart->secondReminder = true;
                        $cart->isScheduled = false;

                        AbandonedCart::$plugin->getCarts()->saveCart($cart);
                        AbandonedCart::$plugin->getCarts()->sendMail($cart, $secondSubject, $cart->email, $secondTemplate);
                    }
                }
            }

            $this->setProgress($queue, $step / $totalSteps);
        }
    }


    // Protected Methods
    // =========================================================================

    protected function defaultDescription(): ?string
    {
        return Craft::t('abandoned-cart', 'Send abandoned cart reminder');
    }
}