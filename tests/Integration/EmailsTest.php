<?php

use verbb\abandonedcart\AbandonedCart;
use verbb\abandonedcart\models\Email;
use verbb\abandonedcart\models\Sequence;

uses(verbb\abandonedcart\tests\Integration\TestCase::class);

function sequenceWithEmails(array $subjects): Sequence
{
    $sequence = new Sequence(['name' => 'Test Sequence']);
    AbandonedCart::$plugin->getSequences()->saveSequence($sequence);

    foreach ($subjects as $subject) {
        AbandonedCart::$plugin->getEmails()->saveEmail(new Email(['sequenceId' => $sequence->id, 'subject' => $subject, 'delayMinutes' => 60]));
    }

    return $sequence;
}

function subjects(Sequence $sequence): array
{
    return array_map(fn(Email $email) => $email->position . ':' . $email->subject, $sequence->getEmails());
}

it('adds emails to the end, and between emails by moving the later ones down', function() {
    $sequence = sequenceWithEmails(['First', 'Second']);
    $between = new Email(['sequenceId' => $sequence->id, 'subject' => 'Between', 'position' => 2]);

    expect(AbandonedCart::$plugin->getEmails()->saveEmail($between))->toBeTrue()
        ->and(subjects($sequence))->toBe(['1:First', '2:Between', '3:Second']);
});

it('allows up to five emails', function() {
    $sequence = sequenceWithEmails(['One', 'Two', 'Three', 'Four', 'Five']);
    $sixth = new Email(['sequenceId' => $sequence->id, 'subject' => 'Six']);

    expect(AbandonedCart::$plugin->getEmails()->saveEmail($sixth))->toBeFalse()
        ->and($sixth->getErrors('position'))->not->toBeEmpty()
        ->and($sequence->getEmails())->toHaveCount(5);
});

it('closes the gap when an email is deleted', function() {
    $sequence = sequenceWithEmails(['First', 'Second', 'Third']);
    $second = $sequence->getEmails()[1];

    expect(AbandonedCart::$plugin->getEmails()->deleteEmail($second))->toBeTrue()
        ->and(subjects($sequence))->toBe(['1:First', '2:Third']);
});

it('keeps an email in place when it is edited', function() {
    $sequence = sequenceWithEmails(['First', 'Second']);
    $first = $sequence->getEmails()[0];
    $first->subject = 'Edited';
    $first->position = 2;

    AbandonedCart::$plugin->getEmails()->saveEmail($first);

    expect(subjects($sequence))->toBe(['1:Edited', '2:Second']);
});

it('requires a subject and an existing sequence', function() {
    $missingSubject = new Email(['sequenceId' => AbandonedCart::$plugin->getSequences()->getFallbackSequence()->id]);
    $missingSequence = new Email(['sequenceId' => 999999, 'subject' => 'Orphan']);

    expect(AbandonedCart::$plugin->getEmails()->saveEmail($missingSubject))->toBeFalse()
        ->and($missingSubject->getErrors('subject'))->not->toBeEmpty()
        ->and(AbandonedCart::$plugin->getEmails()->saveEmail($missingSequence))->toBeFalse()
        ->and($missingSequence->getErrors('sequenceId'))->not->toBeEmpty();
});
