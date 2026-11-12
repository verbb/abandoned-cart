<?php

use verbb\abandonedcart\AbandonedCart;
use verbb\abandonedcart\db\Table;
use verbb\abandonedcart\models\Sequence;

use craft\db\Query;

uses(verbb\abandonedcart\tests\Integration\TestCase::class);

it('creates every 5.0 table and no 4.x table', function() {
    $db = Craft::$app->getDb();

    foreach ([
        Table::SEQUENCES,
        Table::SEQUENCE_VERSIONS,
        Table::EMAILS,
        Table::EMAIL_TEMPLATES,
        Table::CUSTOMERS,
        Table::RECOVERIES,
        Table::ATTEMPTS,
        Table::VISITS,
        Table::ACTIVITIES,
        Table::SKIPPED_CARTS,
        Table::STATE,
        Table::RUNS,
    ] as $table) {
        expect($db->tableExists($table))->toBeTrue("{$table} is missing.");
    }

    expect($db->tableExists(Table::CARTS))->toBeFalse()
        ->and(AbandonedCart::$plugin->schemaVersion)->toBe('5.0.5');
});

it('creates a default email template that uses the bundled design', function() {
    $templates = AbandonedCart::$plugin->getEmailTemplates()->getAllTemplates();
    $default = AbandonedCart::$plugin->getEmailTemplates()->getDefaultTemplate();

    expect($templates)->toHaveCount(1)
        ->and($default?->handle)->toBe('default')
        ->and($default->getUsesBundledDesign())->toBeTrue()
        ->and(Craft::$app->getProjectConfig()->get('abandoned-cart.emailTemplates.' . $default->uid . '.isDefault'))->toBeTrue();
});

it('creates the default sequence, All Other Carts, switched off, with three reminders', function() {
    $sequences = AbandonedCart::$plugin->getSequences()->getAllSequences();
    $fallback = AbandonedCart::$plugin->getSequences()->getFallbackSequence();
    $emails = $fallback->getEmails();

    expect($sequences)->toHaveCount(1)
        ->and($fallback->name)->toBe('All Other Carts')
        ->and($fallback->isFallback)->toBeTrue()
        ->and($fallback->enabled)->toBeFalse()
        ->and($fallback->enrollmentStartedAt)->toBeNull()
        ->and($fallback->audienceType)->toBe(Sequence::AUDIENCE_ALL)
        ->and(array_map(fn($email) => [$email->position, $email->delayMinutes], $emails))->toBe([[1, 0], [2, 1440], [3, 2880]])
        ->and($emails[0]->subject)->toBe('You left something behind')
        ->and($emails[0]->getEmailTemplate()?->handle)->toBe('default')
        ->and((int)(new Query())->from(Table::SEQUENCE_VERSIONS)->count())->toBe(0);
});
