<?php
namespace verbb\abandonedcart\events;

use verbb\abandonedcart\models\Sequence;

use craft\events\CancelableEvent;

class SequenceEvent extends CancelableEvent
{
    // Properties
    // =========================================================================

    public Sequence $sequence;
    public bool $isNew = false;
}
