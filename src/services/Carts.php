<?php
namespace verbb\abandonedcart\services;

use yii\base\Component;

/**
 * Holds the 4.x event names so handlers registered against them keep working while compatibility mode is on.
 *
 * @deprecated in 5.0.0. Listen to `Attempts::EVENT_BEFORE_SEND` instead.
 */
class Carts extends Component
{
    // Constants
    // =========================================================================

    /**
     * @event \verbb\abandonedcart\events\BeforeMailSend Triggered before each email is sent, with the order and message.
     * @deprecated in 5.0.0. Use `Attempts::EVENT_BEFORE_SEND`.
     */
    public const EVENT_BEFORE_MAIL_SEND = 'beforeMailSend';
}
