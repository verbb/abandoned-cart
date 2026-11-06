<?php
namespace verbb\abandonedcart\events;

use verbb\abandonedcart\models\EmailTemplate;

use craft\events\CancelableEvent;

class EmailTemplateEvent extends CancelableEvent
{
    // Properties
    // =========================================================================

    public ?EmailTemplate $template = null;
    public bool $isNew = false;
}
