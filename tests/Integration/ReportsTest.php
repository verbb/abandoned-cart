<?php

use verbb\abandonedcart\AbandonedCart;
use verbb\abandonedcart\models\Recovery;

uses(verbb\abandonedcart\tests\Integration\TestCase::class);

/**
 * Every report query runs on an empty store and on one with a cart in recovery, a recovered cart and a skipped cart,
 * so database-specific SQL fails here rather than on the Overview.
 */
function runEveryReport(): array
{
    $reports = AbandonedCart::$plugin->getReports();
    $start = new DateTime('-30 days');
    $end = new DateTime('+1 day');
    $sequences = AbandonedCart::$plugin->getSequences()->getAllSequences();
    $customer = AbandonedCart::$plugin->getCustomers()->getCustomerByEmail('report.one@example.test');
    $results = [
        'currency' => $reports->getCurrency(),
        'any' => $reports->hasAnyRecoveries(),
        'active' => $reports->countActiveRecoveries(),
        'sequenceStats' => $reports->getSequenceStats($start),
        'rightNow' => $reports->getRightNow(),
        'summary' => $reports->getSummary($start, $end),
        'daily' => $reports->getDailyRevenue($start, $end),
        'reasons' => $reports->getOutcomeReasons($start, $end),
        'recent' => $reports->getRecentRecoveries(),
        'frequent' => $reports->countFrequentAbandoners($start, 1),
        'opportunity' => $reports->getOpportunity($start),
        'cartCounts' => $reports->getCartCounts(),
        'customerCounts' => $reports->getCustomerCounts(),
        'history' => $customer ? $reports->getCustomerHistory($customer->id) : [],
    ];

    foreach ($sequences as $sequence) {
        $results['emailStats'][$sequence->id] = $reports->getEmailStats($sequence->getEmails(), $start);
    }

    foreach (['all', 'recovery', 'recovered', 'not', 'skipped'] as $status) {
        foreach (['abandonedAt', 'cartTotal', 'customer'] as $sort) {
            $results['carts'][$status][$sort] = $reports->getCartList(['status' => $status, 'search' => 'report'], 1, 10, $sort, 'asc');
        }
    }

    foreach (['all', 'active', 'unsubscribed', 'doNotEmail'] as $status) {
        foreach (['carts', 'recovered', 'revenue', 'lastAbandoned', 'customer'] as $sort) {
            $results['customers'][$status][$sort] = $reports->getCustomerList(['status' => $status, 'search' => 'report'], 1, 10, $sort, 'desc');
        }
    }

    $ids = array_map(static fn(array $row): int => (int)$row['id'], $results['carts']['all']['abandonedAt']['rows'] ?? []);
    $results['progress'] = $reports->getEmailProgress($ids);

    return $results;
}

it('runs every report on an empty store', function() {
    $results = runEveryReport();

    expect($results['any'])->toBeFalse()
        ->and($results['summary']['recovered'])->toEqual(0)
        ->and($results['cartCounts']['all'] ?? 0)->toEqual(0);
});

it('runs every report with carts in each state', function() {
    $this->turnOn(AbandonedCart::$plugin->getSequences()->getFallbackSequence());
    $this->createCart('report.one@example.test', idleMinutes: 90);
    $recovered = $this->createCart('report.two@example.test', idleMinutes: 90);
    AbandonedCart::$plugin->getCustomers()->changeStatus(AbandonedCart::$plugin->getCustomers()->getOrCreateCustomer('report.three@example.test'), 'doNotEmail', 'staff');
    $this->createCart('report.three@example.test', idleMinutes: 90);
    AbandonedCart::$plugin->getRecoveries()->run();
    $this->completeOrder($recovered);

    $results = runEveryReport();
    $statuses = array_map(static fn(array $row) => $row['state'], $results['carts']['all']['abandonedAt']['rows']);

    expect($results['any'])->toBeTrue()
        ->and((int)$results['summary']['recovered'])->toBe(1)
        ->and($statuses)->toContain('recovery', 'recovered', 'skipped')
        ->and($results['recent'])->toHaveCount(1)
        ->and($results['history'])->not->toBeEmpty();
});
