<?php
namespace verbb\abandonedcart\events;

use verbb\abandonedcart\models\Recovery;

use yii\base\Event;

class RecoveryEvent extends Event
{
    // Properties
    // =========================================================================

    public Recovery $recovery;
}
