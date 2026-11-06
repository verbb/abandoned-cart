<?php
namespace verbb\abandonedcart\events;

use verbb\abandonedcart\models\Preset;

use yii\base\Event;

class RegisterPresetsEvent extends Event
{
    // Properties
    // =========================================================================

    /** @var Preset[] */
    public array $presets = [];
}
