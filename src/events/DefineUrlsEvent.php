<?php
namespace verbb\abandonedcart\events;

use verbb\abandonedcart\models\Attempt;
use verbb\abandonedcart\models\Recovery;

use yii\base\Event;

class DefineUrlsEvent extends Event
{
    // Properties
    // =========================================================================

    public Recovery $recovery;
    public ?Attempt $attempt = null;
    public string $recoverUrl;
    public string $unsubscribeUrl;
}
