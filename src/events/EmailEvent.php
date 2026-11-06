<?php
namespace verbb\abandonedcart\events;

use verbb\abandonedcart\models\Email;

use craft\events\CancelableEvent;

class EmailEvent extends CancelableEvent
{
    // Properties
    // =========================================================================

    public Email $email;
    public bool $isNew = false;
}
