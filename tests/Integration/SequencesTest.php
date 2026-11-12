<?php

use verbb\abandonedcart\AbandonedCart;
use verbb\abandonedcart\db\Table;
use verbb\abandonedcart\events\SequenceEvent;
use verbb\abandonedcart\models\Email;
use verbb\abandonedcart\models\Sequence;
use verbb\abandonedcart\services\Sequences;

use craft\db\Query;

uses(verbb\abandonedcart\tests\Integration\TestCase::class);

function saveSequence(array $attributes = []): Sequence
{
    $sequence = new Sequence(array_merge(['name' => 'High-Value Carts', 'audienceType' => Sequence::AUDIENCE_CART_VALUE, 'audienceSettings' => ['operator' => 'atLeast', 'amount' => 200]], $attributes));

    expect(AbandonedCart::$plugin->getSequences()->saveSequence($sequence))->toBeTrue(implode(' ', $sequence->getFirstErrors()));

    return $sequence;
}

function versionCount(Sequence $sequence): int
{
    return (int)(new Query())->from(Table::SEQUENCE_VERSIONS)->where(['sequenceId' => $sequence->id])->count();
}

it('generates a unique hidden handle and places new sequences above the default sequence', function() {
    $first = saveSequence();
    $second = saveSequence();
    $order = array_map(fn(Sequence $sequence) => $sequence->handle, AbandonedCart::$plugin->getSequences()->getAllSequences());

    expect($first->handle)->toBe('highValueCarts')
        ->and($second->handle)->toBe('highValueCarts2')
        ->and($order)->toBe(['highValueCarts', 'highValueCarts2', 'everyoneElse'])
        ->and(AbandonedCart::$plugin->getSequences()->getSequenceById($first->id)->audienceSettings)->toBe(['operator' => 'atLeast', 'amount' => 200]);
});

it('keeps the default sequence last whatever order is saved', function() {
    $first = saveSequence();
    $second = saveSequence(['name' => 'Returning Customers', 'audienceType' => Sequence::AUDIENCE_RETURNING]);
    $fallback = AbandonedCart::$plugin->getSequences()->getFallbackSequence();

    AbandonedCart::$plugin->getSequences()->reorderSequences([$fallback->id, $second->id, $first->id]);
    $order = array_map(fn(Sequence $sequence) => $sequence->id, AbandonedCart::$plugin->getSequences()->getAllSequences());

    expect($order)->toBe([$second->id, $first->id, $fallback->id]);
});

it('sets the enrolment start only when a sequence is turned on', function() {
    $sequence = saveSequence();

    expect($sequence->enrollmentStartedAt)->toBeNull();

    $sequence->enabled = true;
    AbandonedCart::$plugin->getSequences()->saveSequence($sequence);
    $started = AbandonedCart::$plugin->getSequences()->getSequenceById($sequence->id)->enrollmentStartedAt;

    // Saving again while on leaves the start alone, so carts waiting on the scheduler aren't lost.
    $sequence->name = 'Big Carts';
    AbandonedCart::$plugin->getSequences()->saveSequence($sequence);

    expect($started)->not->toBeNull()
        ->and(AbandonedCart::$plugin->getSequences()->getSequenceById($sequence->id)->enrollmentStartedAt)->toEqual($started);
});

it('protects the default sequence', function() {
    $fallback = AbandonedCart::$plugin->getSequences()->getFallbackSequence();
    $second = new Sequence(['name' => 'Another Fallback', 'isFallback' => true]);

    $fallback->audienceType = Sequence::AUDIENCE_RETURNING;

    expect(AbandonedCart::$plugin->getSequences()->saveSequence($fallback))->toBeFalse()
        ->and($fallback->getErrors('audienceType'))->not->toBeEmpty()
        ->and(AbandonedCart::$plugin->getSequences()->saveSequence($second))->toBeFalse()
        ->and($second->getErrors('isFallback'))->not->toBeEmpty()
        ->and(AbandonedCart::$plugin->getSequences()->deleteSequence(AbandonedCart::$plugin->getSequences()->getFallbackSequence()))->toBeFalse();
});

it('gives entering carts a new version only when timing, audience or the schedule has changed', function() {
    $sequence = saveSequence();
    $sequences = AbandonedCart::$plugin->getSequences();
    $first = $sequences->getCurrentVersionId($sequence);

    $sequence->name = 'Renamed';
    $sequences->saveSequence($sequence);
    $afterRename = $sequences->getCurrentVersionId($sequence);

    $sequence->attributionWindowMinutes = 1440;
    $sequences->saveSequence($sequence);
    $afterTiming = $sequences->getCurrentVersionId($sequence);

    $email = new Email(['sequenceId' => $sequence->id, 'subject' => 'Your cart is saved']);
    AbandonedCart::$plugin->getEmails()->saveEmail($email);
    $afterEmail = $sequences->getCurrentVersionId($sequence);

    // Wording is read live, so a new subject reaches unsent emails without a new version.
    $email->subject = 'A new subject';
    AbandonedCart::$plugin->getEmails()->saveEmail($email);
    $afterWording = $sequences->getCurrentVersionId($sequence);

    expect($afterRename)->toBe($first)
        ->and($afterTiming)->not->toBe($first)
        ->and($afterEmail)->not->toBe($afterTiming)
        ->and($afterWording)->toBe($afterEmail)
        ->and(versionCount($sequence))->toBe(3);
});

it('deletes a sequence with its emails but keeps its versions', function() {
    $sequence = saveSequence();
    AbandonedCart::$plugin->getEmails()->saveEmail(new Email(['sequenceId' => $sequence->id, 'subject' => 'Hello']));
    AbandonedCart::$plugin->getSequences()->getCurrentVersionId($sequence);

    expect(AbandonedCart::$plugin->getSequences()->deleteSequence($sequence))->toBeTrue()
        ->and(AbandonedCart::$plugin->getEmails()->getEmailsBySequenceId($sequence->id))->toBe([])
        ->and((int)(new Query())->from(Table::SEQUENCE_VERSIONS)->where(['sequenceId' => null])->count())->toBeGreaterThan(0);
});

it('lets a handler cancel a save and see it afterwards', function() {
    $sequences = AbandonedCart::$plugin->getSequences();
    $seen = [];
    $cancel = function(SequenceEvent $event) {
        $event->isValid = $event->sequence->name !== 'Blocked';
    };
    $after = function(SequenceEvent $event) use (&$seen) {
        $seen[] = [$event->sequence->name, $event->isNew];
    };
    $sequences->on(Sequences::EVENT_BEFORE_SAVE_SEQUENCE, $cancel);
    $sequences->on(Sequences::EVENT_AFTER_SAVE_SEQUENCE, $after);

    try {
        $blocked = new Sequence(['name' => 'Blocked']);

        expect($sequences->saveSequence($blocked))->toBeFalse()
            ->and($blocked->id)->toBeNull();

        saveSequence(['name' => 'Allowed']);
    } finally {
        $sequences->off(Sequences::EVENT_BEFORE_SAVE_SEQUENCE, $cancel);
        $sequences->off(Sequences::EVENT_AFTER_SAVE_SEQUENCE, $after);
    }

    expect($seen)->toBe([['Allowed', true]]);
});

it('renames the default sequence to All Other Carts unless the store has renamed it', function() {
    $sequences = AbandonedCart::$plugin->getSequences();
    $db = Craft::$app->getDb();
    $id = $sequences->getFallbackSequence()->id;
    $migration = new verbb\abandonedcart\migrations\m261107_010000_rename_default_sequence();
    $name = fn() => (new craft\db\Query())->select(['name'])->from([verbb\abandonedcart\db\Table::SEQUENCES])->where(['id' => $id])->scalar();

    $db->createCommand()->update(verbb\abandonedcart\db\Table::SEQUENCES, ['name' => 'Everyone Else'], ['id' => $id])->execute();
    $migration->safeUp();
    expect($name())->toBe('All Other Carts');

    $db->createCommand()->update(verbb\abandonedcart\db\Table::SEQUENCES, ['name' => 'Our Usual Emails'], ['id' => $id])->execute();
    $migration->safeUp();
    expect($name())->toBe('Our Usual Emails');
});

it('matches the Products audience on the products chosen, and nothing else', function() {
    $audiences = AbandonedCart::$plugin->getAudiences();
    $wallet = $this->createVariant();
    $bag = $this->createVariant();
    $cart = $this->createCart('products@example.test', $wallet);
    $sequence = saveSequence(['audienceType' => Sequence::AUDIENCE_PRODUCTS, 'audienceSettings' => ['match' => 'any', 'productIds' => [$wallet->primaryOwnerId]]]);

    expect($audiences->matches($sequence, $cart))->toBeTrue();

    $sequence->audienceSettings = ['match' => 'all', 'productIds' => [$wallet->primaryOwnerId, $bag->primaryOwnerId]];
    expect($audiences->matches($sequence, $cart))->toBeFalse();

    // There's no category setting. One left in a sequence's settings is ignored.
    $sequence->audienceSettings = ['match' => 'any', 'productIds' => [], 'categoryIds' => [123]];
    expect($audiences->matches($sequence, $cart))->toBeFalse();
});
