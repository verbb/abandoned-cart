<?php

use verbb\abandonedcart\AbandonedCart;
use verbb\abandonedcart\db\Table;
use verbb\abandonedcart\services\Runs;
use verbb\abandonedcart\services\State;

use craft\db\Query;
use craft\helpers\Db;
use craft\helpers\StringHelper;

uses(verbb\abandonedcart\tests\Integration\TestCase::class);

function addRun(string $trigger, string $ago, array $counts = []): void
{
    Db::insert(Table::RUNS, array_merge([
        'trigger' => $trigger,
        'startedAt' => Db::prepareDateForDb(new DateTime($ago)),
        'uid' => StringHelper::UUID(),
    ], $counts));
}

it('records each run with what started it, and the command line’s PHP for console runs', function() {
    AbandonedCart::$plugin->getRecoveries()->run(null, Runs::TRIGGER_CONSOLE);

    $row = (new Query())->from([Table::RUNS])->orderBy(['id' => SORT_DESC])->one();

    expect($row['trigger'])->toBe(Runs::TRIGGER_CONSOLE)
        ->and(AbandonedCart::$plugin->getState()->get(State::CLI_PHP_BINARY))->toBe(PHP_BINARY);
});

it('works out how scheduled runs are being started', function() {
    $runs = AbandonedCart::$plugin->getRuns();
    Db::delete(Table::RUNS);

    expect($runs->getSchedulerMode())->toBeNull();

    addRun(Runs::TRIGGER_CONSOLE, '-1 minute');
    expect($runs->getSchedulerMode())->toBe(Runs::MODE_CONSOLE);

    addRun(Runs::TRIGGER_CONSOLE, '-6 minutes');
    addRun(Runs::TRIGGER_CONSOLE, '-11 minutes');
    addRun(Runs::TRIGGER_RUN_NOW, '-30 seconds');
    expect($runs->getSchedulerMode())->toBe(Runs::MODE_CRON);

    addRun(Runs::TRIGGER_QUEUE, '-10 seconds');
    expect($runs->getSchedulerMode())->toBe(Runs::MODE_QUEUE);
});

it('summarises the last day in half-hour slots and keeps a week of runs', function() {
    $runs = AbandonedCart::$plugin->getRuns();
    Db::delete(Table::RUNS);
    addRun(Runs::TRIGGER_CONSOLE, '-2 minutes', ['sent' => 3]);
    addRun(Runs::TRIGGER_CONSOLE, '-4 minutes', ['sent' => 1, 'failed' => 1]);
    addRun(Runs::TRIGGER_CONSOLE, '-8 days');

    $day = $runs->getDay();
    $busy = array_values(array_filter($day, fn(array $slot) => $slot['runs'] > 0));

    expect($day)->toHaveCount(48)
        ->and(array_sum(array_column($day, 'runs')))->toBe(2)
        ->and(array_sum(array_column($busy, 'sent')))->toBe(4)
        ->and(array_sum(array_column($busy, 'failed')))->toBe(1);

    $runs->purge();

    expect((int)(new Query())->from([Table::RUNS])->count())->toBe(2);
});
