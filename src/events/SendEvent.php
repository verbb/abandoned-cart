<?php
namespace verbb\abandonedcart\events;

use verbb\abandonedcart\models\Attempt;
use verbb\abandonedcart\models\Email;
use verbb\abandonedcart\models\Recovery;
use verbb\abandonedcart\models\RenderedEmail;

use craft\events\CancelableEvent;
use craft\mail\Message;

class SendEvent extends CancelableEvent
{
    // Properties
    // =========================================================================

    public Attempt $attempt;
    public Recovery $recovery;
    public Email $email;
    public ?RenderedEmail $rendered = null;
    public ?Message $message = null;
    public bool $success = false;
}
