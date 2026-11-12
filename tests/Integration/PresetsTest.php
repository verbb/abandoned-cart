<?php

use verbb\abandonedcart\AbandonedCart;
use verbb\abandonedcart\events\RegisterAudiencesEvent;
use verbb\abandonedcart\events\RegisterPresetsEvent;
use verbb\abandonedcart\models\Email;
use verbb\abandonedcart\models\Preset;
use verbb\abandonedcart\models\Sequence;
use verbb\abandonedcart\services\Audiences;
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

it('accepts presets for audiences a module registers', function() {
    $audiences = new Audiences();
    $audiences->on(Audiences::EVENT_REGISTER_AUDIENCES, function(RegisterAudiencesEvent $event) {
        $event->audiences['wholesale'] = ['label' => 'Wholesale Customers', 'description' => 'Trade accounts'];
    });
    $original = AbandonedCart::$plugin->getAudiences();
    AbandonedCart::$plugin->set('audiences', $audiences);

    try {
        $presets = new Presets();
        $presets->on(Presets::EVENT_REGISTER_PRESETS, function(RegisterPresetsEvent $event) {
            $event->presets[] = ['handle' => 'tradeFollowUp', 'name' => 'Trade Follow-Up', 'audienceType' => 'wholesale', 'emails' => [['subject' => 'Your order is waiting']]];
        });

        $handles = array_map(fn(Preset $preset) => $preset->handle, $presets->getAllPresets());
    } finally {
        AbandonedCart::$plugin->set('audiences', $original);
    }

    expect($handles)->toContain('tradeFollowUp');
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

it('keeps the built-in presets in project config, where Settings can change them', function() {
    $presets = AbandonedCart::$plugin->getPresets();
    $settingsPresets = $presets->getSettingsPresets();

    expect(array_map(fn(Preset $preset) => $preset->handle, $settingsPresets))->toBe(['threeReminders', 'remindersThenDiscount', 'highValueCarts', 'singleReminder'])
        ->and(array_unique(array_map(fn(Preset $preset) => $preset->isEditable, $settingsPresets)))->toBe([true])
        ->and(Craft::$app->getProjectConfig()->get(Presets::CONFIG_KEY . '.' . $settingsPresets[0]->uid . '.handle'))->toBe('threeReminders');
});

it('saves, reorders and deletes presets, and keeps handles unique', function() {
    $presets = AbandonedCart::$plugin->getPresets();
    $preset = new Preset([
        'name' => 'VIP Win-Back',
        'handle' => 'vipWinBack',
        'audienceType' => Sequence::AUDIENCE_RETURNING,
        'emails' => [['delayMinutes' => 60, 'subject' => 'Your cart is saved']],
    ]);

    expect($presets->savePreset($preset))->toBeTrue()
        ->and($presets->getPresetByHandle('vipWinBack')?->isEditable)->toBeTrue()
        ->and(array_map(fn(Preset $p) => $p->handle, $presets->getAllPresets()))->toBe(['threeReminders', 'remindersThenDiscount', 'highValueCarts', 'singleReminder', 'vipWinBack']);

    $duplicate = new Preset(['name' => 'Another', 'handle' => 'vipWinBack', 'emails' => [['subject' => 'Hi']]]);
    $noSubject = new Preset(['name' => 'Empty', 'handle' => 'empty', 'emails' => [['delayMinutes' => 0]]]);

    expect($presets->savePreset($duplicate))->toBeFalse()
        ->and($duplicate->getErrors('handle'))->not->toBeEmpty()
        ->and($presets->savePreset($noSubject))->toBeFalse()
        ->and($noSubject->getErrors('emails'))->not->toBeEmpty();

    $uids = array_map(fn(Preset $p) => $p->uid, $presets->getSettingsPresets());
    $presets->reorderPresets([$uids[4], ...array_slice($uids, 0, 4)]);

    expect($presets->getAllPresets()[0]->handle)->toBe('vipWinBack');

    expect($presets->deletePreset($presets->getSettingsPresetByUid($preset->uid)))->toBeTrue()
        ->and($presets->getPresetByHandle('vipWinBack'))->toBeNull();
});

it('lists presets a plugin adds as read-only', function() {
    $presets = new Presets();
    $presets->on(Presets::EVENT_REGISTER_PRESETS, function(RegisterPresetsEvent $event) {
        $event->presets[] = new Preset(['handle' => 'fromPlugin', 'name' => 'From a Plugin', 'emails' => [['subject' => 'Hello']]]);
    });

    expect(array_map(fn(Preset $p) => $p->handle, $presets->getPluginPresets()))->toBe(['fromPlugin'])
        ->and($presets->getPresetByHandle('fromPlugin')->getCpEditUrl())->toBeNull()
        ->and($presets->deletePreset($presets->getPresetByHandle('fromPlugin')))->toBeFalse();
});
