<?php

use verbb\abandonedcart\AbandonedCart;
use verbb\abandonedcart\events\BeforeMailSend;
use verbb\abandonedcart\models\Attempt;
use verbb\abandonedcart\services\Carts;

use yii\base\Event;

uses(verbb\abandonedcart\tests\Integration\TestCase::class);

it('passes each email through 4.x beforeMailSend handlers in compatibility mode', function() {
    $this->turnOn(AbandonedCart::$plugin->getSequences()->getFallbackSequence());
    $order = $this->createCart('legacy.handler@example.test', idleMinutes: 90);
    $seen = [];
    $handler = function(BeforeMailSend $event) use (&$seen) {
        $seen[] = $event->order->id;
        $event->message->setSubject('Changed by a 4.x handler');
    };
    Event::on(Carts::class, Carts::EVENT_BEFORE_MAIL_SEND, $handler);

    try {
        AbandonedCart::$plugin->getRecoveries()->run();
    } finally {
        Event::off(Carts::class, Carts::EVENT_BEFORE_MAIL_SEND, $handler);
    }

    expect($seen)->toBe([$order->id]);
});

it('lets a 4.x handler stop an email, and ignores 4.x handlers with compatibility mode off', function() {
    $this->turnOn(AbandonedCart::$plugin->getSequences()->getFallbackSequence());
    $this->createCart('legacy.cancel@example.test', idleMinutes: 90);
    $calls = 0;
    $handler = function(BeforeMailSend $event) use (&$calls) {
        $calls++;
        $event->isValid = false;
    };
    Event::on(Carts::class, Carts::EVENT_BEFORE_MAIL_SEND, $handler);

    try {
        $summary = AbandonedCart::$plugin->getRecoveries()->run();
        expect($summary['sent'])->toBe(0)->and($calls)->toBe(1);

        AbandonedCart::$plugin->getSettings()->compatibilityMode = false;
        $this->createCart('modern.only@example.test', idleMinutes: 90);
        $summary = AbandonedCart::$plugin->getRecoveries()->run();
    } finally {
        Event::off(Carts::class, Carts::EVENT_BEFORE_MAIL_SEND, $handler);
    }

    expect($summary['sent'])->toBe(1)->and($calls)->toBe(1);
});
