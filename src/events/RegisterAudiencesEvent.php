<?php
namespace verbb\abandonedcart\events;

use yii\base\Event;

class RegisterAudiencesEvent extends Event
{
    // Properties
    // =========================================================================

    /** @var array<string, array{label: string, description: string}> keyed by audience type */
    public array $audiences = [];
}
