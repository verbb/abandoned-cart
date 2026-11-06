<?php

use verbb\abandonedcart\AbandonedCart;
use verbb\abandonedcart\db\Table;
use verbb\abandonedcart\migrations\Install;
use verbb\abandonedcart\models\Attempt;
use verbb\abandonedcart\models\Customer;
use verbb\abandonedcart\models\Recovery;

use craft\db\Migration;
use craft\db\Query;
use craft\db\Table as CraftTable;
use craft\helpers\Db;
use craft\helpers\StringHelper;

use craft\commerce\db\Table as CommerceTable;
use craft\commerce\elements\Order;

uses(verbb\abandonedcart\tests\Integration\UpgradeTestCase::class);

/**
 * Turns this install back into a 3.x (Craft 4) or 4.x (Craft 5) one: the 4.x carts table, the 4.x settings in project
 * config and the migration history of that release. `$fourX` also runs the 4.x migrations, so rows can carry the
 * columns they added.
 */
function rebuildLegacyInstall(array $settings, bool $fourX): void
{
    $db = Craft::$app->getDb();
    $projectConfig = Craft::$app->getProjectConfig();

    $projectConfig->muteEvents = true;
    $projectConfig->remove('abandoned-cart');
    $projectConfig->muteEvents = false;
    (new Install())->dropTables();

    $migration = new class(['db' => $db, 'compact' => true]) extends Migration {
        public function safeUp(): bool
        {
            return true;
        }
    };
    $migration->createTable(Table::CARTS, [
        'id' => $migration->primaryKey(),
        'orderId' => $migration->integer()->notNull(),
        'email' => $migration->string()->notNull()->defaultValue(''),
        'clicked' => $migration->boolean()->defaultValue(false),
        'isScheduled' => $migration->boolean()->defaultValue(false),
        'firstReminder' => $migration->boolean()->defaultValue(false),
        'secondReminder' => $migration->boolean()->defaultValue(false),
        'isRecovered' => $migration->boolean()->defaultValue(false),
        'isSent' => $migration->boolean()->defaultValue(false),
        'dateCreated' => $migration->dateTime()->notNull(),
        'dateUpdated' => $migration->dateTime()->notNull(),
        'uid' => $migration->uid(),
    ]);
    $migration->createIndex(null, Table::CARTS, ['orderId']);
    $migration->addForeignKey(null, Table::CARTS, ['orderId'], CommerceTable::ORDERS, ['id'], 'CASCADE', null);

    $legacy = ['m261002_000000_recipient_controls', 'm261003_000000_recovery_expiry_anchor', 'm261003_010000_unique_order_id', 'm261106_000000_upgrade_to_5'];
    Db::delete(CraftTable::MIGRATIONS, ['track' => 'plugin:abandoned-cart', 'name' => $legacy]);

    $projectConfig->set('plugins.abandoned-cart.settings', $settings);
    $projectConfig->set('plugins.abandoned-cart.schemaVersion', $fourX ? '2.3.0' : '1.0.1');
    resetPluginComponents();

    if ($fourX) {
        $migrator = AbandonedCart::$plugin->getMigrator();

        foreach (array_slice($legacy, 0, 3) as $name) {
            $migrator->migrateUp($name);
        }
    }
}

function resetPluginComponents(): void
{
    $plugin = AbandonedCart::$plugin;

    foreach ($plugin->getComponents(true) as $id => $definition) {
        if (!is_object($definition)) {
            $plugin->set($id, $definition);
        }
    }
}

function insertLegacyCart(Order $order, array $values): void
{
    $now = new DateTime();

    Craft::$app->getDb()->createCommand()->insert(Table::CARTS, [
        'orderId' => $order->id,
        'email' => $order->getEmail(),
        'uid' => StringHelper::UUID(),
        ...$values,
        'dateCreated' => Db::prepareDateForDb($values['dateCreated'] ?? $now),
        'dateUpdated' => Db::prepareDateForDb($values['dateUpdated'] ?? $now),
    ], false)->execute();
}

function upgradeToFive(): void
{
    AbandonedCart::$plugin->getMigrator()->up();
    resetPluginComponents();
}

function restoreFreshInstall(array $orders): void
{
    foreach ($orders as $order) {
        if ($order = Order::find()->id($order->id)->status(null)->one()) {
            Craft::$app->getElements()->deleteElement($order, true);
        }
    }

    $plugins = Craft::$app->getPlugins();

    if ($plugins->isPluginInstalled('abandoned-cart')) {
        $plugins->uninstallPlugin('abandoned-cart');
    }

    $plugins->installPlugin('abandoned-cart');
    resetPluginComponents();
}

function recoveryForOrder(Order $order): ?Recovery
{
    $id = (new Query())->select(['id'])->from(Table::RECOVERIES)->where(['orderId' => $order->id])->scalar();

    return $id ? AbandonedCart::$plugin->getRecoveries()->getRecoveryById((int)$id) : null;
}

it('upgrades a 4.x install on Craft 5', function() {
    $orders = [];

    try {
        $orders['recovered'] = $this->createCart('back.again@example.test', idleMinutes: 300);
        $orders['partWay'] = $this->createCart('part.way@example.test', idleMinutes: 300);
        $orders['finished'] = $this->createCart('both.sent@example.test', idleMinutes: 300);
        $orders['unsent'] = $this->createCart('never.sent@example.test', idleMinutes: 300);
        $this->completeOrder($orders['recovered']);

        rebuildLegacyInstall([
            'pluginName' => 'Cart Reminders',
            'passKey' => 'legacy-pass-key',
            'restoreExpiryHours' => 72,
            'firstReminderDelay' => 2,
            'secondReminderDelay' => 26,
            'recipientCooldownHours' => 48,
            'maxEnrollmentsPerRun' => 100,
            'staleRecordRetentionDays' => 90,
            'discountCode' => 'WELCOME10',
            'firstReminderTemplate' => '_emails/cart-first',
            'secondReminderTemplate' => 'abandoned-cart/emails/second',
            'firstReminderSubject' => 'Still thinking it over?',
            'secondReminderSubject' => 'Last call for your cart',
            'recoveryUrl' => 'shop/checkout',
            'disableSecondReminder' => false,
            'previousOrderRequired' => true,
            'blacklist' => 'Blocked@Example.test, spam.example, @junk.example',
            'includeBlacklisted' => true,
        ], true);

        $hoursAgo = static fn(int $hours): DateTime => new DateTime("-$hours hours");
        insertLegacyCart($orders['recovered'], ['firstReminder' => true, 'secondReminder' => true, 'isSent' => true, 'clicked' => true, 'isRecovered' => true, 'dateCreated' => $hoursAgo(40), 'dateLastSent' => Db::prepareDateForDb($hoursAgo(2)), 'recipientKey' => 'back.again@example.test']);
        insertLegacyCart($orders['partWay'], ['firstReminder' => true, 'isSent' => true, 'isScheduled' => false, 'dateCreated' => $hoursAgo(5), 'dateLastSent' => Db::prepareDateForDb($hoursAgo(3)), 'recipientKey' => 'part.way@example.test']);
        insertLegacyCart($orders['finished'], ['firstReminder' => true, 'secondReminder' => true, 'isSent' => true, 'dateCreated' => $hoursAgo(240), 'dateLastSent' => Db::prepareDateForDb($hoursAgo(214))]);
        insertLegacyCart($orders['unsent'], ['isScheduled' => true, 'dateCreated' => $hoursAgo(1)]);

        upgradeToFive();

        $plugin = AbandonedCart::$plugin;
        $settings = $plugin->getSettings();
        $sequence = $plugin->getSequences()->getFallbackSequence();
        $emails = $sequence->getEmails();
        $customTemplate = $plugin->getEmailTemplates()->getTemplateById((int)$emails[0]->emailTemplateId);
        $blocked = $plugin->getCustomers()->getCustomerByEmail('blocked@example.test');
        $partWay = recoveryForOrder($orders['partWay']);
        $partWayAttempts = $partWay->getAttempts();

        expect(Craft::$app->getDb()->tableExists(Table::CARTS))->toBeFalse()
            ->and($plugin->getEmailTemplates()->getDefaultTemplate())->not->toBeNull()
            ->and($settings->pluginName)->toBe('Cart Reminders')
            ->and($settings->returnUrl)->toBe('shop/checkout')
            ->and($settings->recoveryWindowMinutes)->toBe(4320)
            ->and($settings->contactLimitMinutes)->toBe(2880)
            ->and($settings->returningCustomersOnly)->toBeTrue()
            ->and($settings->blockedDomains)->toBe(['spam.example', 'junk.example'])
            ->and($settings->historyRetentionDays)->toBe(365)
            ->and($sequence->enabled)->toBeTrue()
            ->and($sequence->enrollmentStartedAt)->not->toBeNull()
            ->and(array_map(fn($email) => $email->delayMinutes, $emails))->toBe([120, 1440])
            ->and(array_map(fn($email) => $email->subject, $emails))->toBe(['Still thinking it over?', 'Last call for your cart'])
            ->and(array_map(fn($email) => $email->discountCode, $emails))->toBe(['WELCOME10', 'WELCOME10'])
            ->and($customTemplate?->htmlTemplate)->toBe('_emails/cart-first')
            ->and($emails[1]->emailTemplateId)->toBeNull()
            ->and($blocked?->status)->toBe(Customer::STATUS_DO_NOT_EMAIL)
            ->and($blocked?->statusSource)->toBe(Customer::SOURCE_UPGRADE)
            ->and(recoveryForOrder($orders['recovered'])?->status)->toBe(Recovery::STATUS_RECOVERED)
            ->and(recoveryForOrder($orders['recovered'])?->clickedBeforeOrder)->toBeTrue()
            ->and(recoveryForOrder($orders['finished'])?->endReason)->toBe(Recovery::END_FINISHED)
            ->and(recoveryForOrder($orders['unsent']))->toBeNull()
            ->and($partWay->status)->toBe(Recovery::STATUS_ACTIVE)
            ->and(array_map(fn(Attempt $attempt) => $attempt->status, $partWayAttempts))->toBe([Attempt::STATUS_SENT, Attempt::STATUS_SCHEDULED])
            ->and(abs($partWayAttempts[1]->dueAt->getTimestamp() - (new DateTime('+21 hours'))->getTimestamp()))->toBeLessThan(120);
    } finally {
        restoreFreshInstall($orders);
    }
});

it('upgrades a 3.x install from Craft 4, running the 4.x migrations on the way', function() {
    $orders = [];

    try {
        $orders['sent'] = $this->createCart('one.reminder@example.test', idleMinutes: 300);
        $orders['recovered'] = $this->createCart('came.back@example.test', idleMinutes: 300);
        $this->completeOrder($orders['recovered']);

        rebuildLegacyInstall([
            'pluginName' => 'Abandoned Carts',
            'passKey' => 'legacy-pass-key',
            'restoreExpiryHours' => 48,
            'firstReminderDelay' => 1,
            'secondReminderDelay' => 12,
            'disableSecondReminder' => true,
            'recoveryUrl' => 'shop/cart',
            'blacklist' => '',
        ], false);

        insertLegacyCart($orders['sent'], ['firstReminder' => true, 'isSent' => true, 'dateCreated' => new DateTime('-3 hours'), 'dateUpdated' => new DateTime('-2 hours')]);
        insertLegacyCart($orders['recovered'], ['firstReminder' => true, 'isSent' => true, 'clicked' => true, 'isRecovered' => true, 'dateCreated' => new DateTime('-30 hours'), 'dateUpdated' => new DateTime('-26 hours')]);

        upgradeToFive();

        $plugin = AbandonedCart::$plugin;
        $sequence = $plugin->getSequences()->getFallbackSequence();
        $emails = $sequence->getEmails();
        $applied = (new Query())->select(['name'])->from(CraftTable::MIGRATIONS)->where(['track' => 'plugin:abandoned-cart'])->column();

        expect($applied)->toContain('m261002_000000_recipient_controls', 'm261003_000000_recovery_expiry_anchor', 'm261003_010000_unique_order_id', 'm261106_000000_upgrade_to_5')
            ->and(Craft::$app->getDb()->tableExists(Table::CARTS))->toBeFalse()
            ->and($sequence->enabled)->toBeTrue()
            ->and($emails)->toHaveCount(1)
            ->and($emails[0]->delayMinutes)->toBe(60)
            ->and($emails[0]->emailTemplateId)->toBeNull()
            ->and($plugin->getSettings()->recoveryWindowMinutes)->toBe(2880)
            ->and($plugin->getSettings()->blockedDomains)->toBe([])
            ->and(recoveryForOrder($orders['sent'])?->endReason)->toBe(Recovery::END_FINISHED)
            ->and(recoveryForOrder($orders['recovered'])?->status)->toBe(Recovery::STATUS_RECOVERED);
    } finally {
        restoreFreshInstall($orders);
    }
});

it('runs safely on a fresh 5.0 install', function() {
    $sequences = count(AbandonedCart::$plugin->getSequences()->getAllSequences());

    expect((new verbb\abandonedcart\migrations\m261106_000000_upgrade_to_5())->safeUp())->toBeTrue()
        ->and(AbandonedCart::$plugin->getSequences()->getAllSequences())->toHaveCount($sequences);
});
