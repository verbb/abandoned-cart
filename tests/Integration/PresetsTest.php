<?php

use verbb\abandonedcart\events\RegisterPresetsEvent;
use verbb\abandonedcart\models\Email;
use verbb\abandonedcart\models\Preset;
use verbb\abandonedcart\models\Sequence;
use verbb\abandonedcart\services\Presets;

uses(verbb\abandonedcart\tests\Integration\TestCase::class);

it('offers the four built-in presets in order', function() {
    $handles = array_map(fn(Preset $preset) => $preset->handle, (new Presets())->getAllPresets());

    expect($handles)->toBe(['threeReminders', 'remindersThenDiscount', 'highValueCarts', 'singleReminder']);
});

it('lets a module add presets, remove built-in ones, and ignores invalid ones', function() {
    $presets = new Presets();
    $presets->on(Presets::EVENT_REGISTER_PRESETS, function(RegisterPresetsEvent $event) {
        $event->presets = array_values(array_filter($event->presets, fn(Preset $preset) => $preset->handle !== 'singleReminder'));
        $event->presets[] = ['handle' => 'vipWinBack', 'name' => 'VIP Win-Back', 'audienceType' => Sequence::AUDIENCE_RETURNING, 'emails' => [['subject' => 'Your cart is saved']]];
        $event->presets[] = ['handle' => 'broken', 'name' => 'Broken', 'audienceType' => 'nonsense'];
    });

    $handles = array_map(fn(Preset $preset) => $preset->handle, $presets->getAllPresets());

    expect($handles)->toBe(['threeReminders', 'remindersThenDiscount', 'highValueCarts', 'vipWinBack']);
});

it('creates a switched-off copy and notes anything the site doesn’t have', function() {
    $presets = new Presets();
    $preset = new Preset([
        'handle' => 'vipWinBack',
        'name' => 'VIP Win-Back',
        'audienceType' => Sequence::AUDIENCE_RETURNING,
        'audienceSettings' => ['minOrders' => 5],
        'overrides' => ['attributionWindowMinutes' => 1440],
        'emails' => [
            ['subject' => 'Your cart is saved', 'delayMinutes' => 120],
            ['subject' => 'On us: free shipping', 'delayMinutes' => 2880, 'discountCode' => 'FREESHIP', 'emailTemplate' => 'missing'],
        ],
    ]);
    $notices = [];
    $sequence = $presets->createSequenceFromPreset($preset, [], $notices);

    // Changing the preset afterwards doesn't touch the sequence made from it.
    $preset->emails[0]['subject'] = 'Changed later';
    $emails = $sequence->getEmails();

    expect($sequence->hasErrors())->toBeFalse()
        ->and($sequence->enabled)->toBeFalse()
        ->and($sequence->audienceSettings)->toBe(['minOrders' => 5])
        ->and($sequence->attributionWindowMinutes)->toBe(1440)
        ->and(array_map(fn(Email $email) => [$email->subject, $email->delayMinutes, $email->discountCode, $email->emailTemplateId], $emails))->toBe([
            ['Your cart is saved', 120, null, null],
            ['On us: free shipping', 2880, 'FREESHIP', null],
        ])
        ->and($notices)->toHaveCount(1);
});
