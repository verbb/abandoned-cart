<?php

use verbb\abandonedcart\AbandonedCart;
use verbb\abandonedcart\models\Attempt;
use verbb\abandonedcart\models\Customer;
use verbb\abandonedcart\models\Recovery;
use verbb\abandonedcart\models\Sequence;
use verbb\abandonedcart\models\SkippedCart;
use verbb\abandonedcart\services\Presets;

uses(verbb\abandonedcart\tests\Integration\TestCase::class);

function engine(): verbb\abandonedcart\services\Recoveries
{
    return AbandonedCart::$plugin->getRecoveries();
}

function tokenFrom(string $url): string
{
    preg_match('#(?:recover|unsubscribe)/([A-Za-z0-9_-]+)#', urldecode($url), $matches);

    return $matches[1] ?? '';
}

function attemptStatuses(Recovery $recovery): array
{
    return array_map(fn(Attempt $attempt) => $attempt->status, $recovery->getAttempts());
}

it('does nothing while every sequence is off', function() {
    $this->createCart('waiting@example.test');

    expect(engine()->run())->toMatchArray(['enrolled' => 0, 'skipped' => 0, 'sent' => 0]);
});

it('enrols an idle cart in the default sequence and sends the first email', function() {
    $this->turnOn(AbandonedCart::$plugin->getSequences()->getFallbackSequence());
    $order = $this->createCart('amelia.hart@example.test', idleMinutes: 90);
    $before = $this->sentMailCount();

    $summary = engine()->run();
    $recovery = engine()->getRecoveriesByCustomerId(AbandonedCart::$plugin->getCustomers()->getCustomerByEmail('amelia.hart@example.test')->id)[0];

    expect($summary)->toMatchArray(['enrolled' => 1, 'sent' => 1])
        ->and($recovery->orderId)->toBe($order->id)
        ->and($recovery->status)->toBe(Recovery::STATUS_ACTIVE)
        ->and(attemptStatuses($recovery))->toBe([Attempt::STATUS_SENT, Attempt::STATUS_SCHEDULED, Attempt::STATUS_SCHEDULED])
        ->and($this->sentMailCount())->toBe($before + 1);
});

it('waits for a cart that hasn’t been idle long enough', function() {
    $this->turnOn(AbandonedCart::$plugin->getSequences()->getFallbackSequence());
    $this->createCart('still.shopping@example.test', idleMinutes: 20);

    expect(engine()->run())->toMatchArray(['enrolled' => 0, 'skipped' => 0]);
});

it('records why carts are skipped', function() {
    $this->turnOn(AbandonedCart::$plugin->getSequences()->getFallbackSequence());
    $customers = AbandonedCart::$plugin->getCustomers();
    $customers->changeStatus($customers->getOrCreateCustomer('blocked@example.test'), Customer::STATUS_DO_NOT_EMAIL, Customer::SOURCE_STAFF);
    AbandonedCart::$plugin->getSettings()->blockedDomains = ['competitor.example'];

    $this->createCart('blocked@example.test');
    $this->createCart('buyer@competitor.example');
    engine()->run();

    $reasons = (new craft\db\Query())->select(['reason'])->from('{{%abandonedcart_skippedcarts}}')->orderBy(['reason' => SORT_ASC])->column();

    expect($reasons)->toBe([SkippedCart::REASON_BLOCKED_DOMAIN, SkippedCart::REASON_DO_NOT_EMAIL]);
});

it('gives each cart the first sequence it matches, from the top', function() {
    $presets = AbandonedCart::$plugin->getPresets();
    $highValue = $presets->createSequenceFromPreset($presets->getPresetByHandle(Presets::PRESET_HIGH_VALUE_CARTS), ['audienceSettings' => ['operator' => 'atLeast', 'amount' => 100]]);
    $this->turnOn($highValue);
    $this->turnOn(AbandonedCart::$plugin->getSequences()->getFallbackSequence());

    $this->createCart('big@example.test', $this->createVariant(150));
    $this->createCart('small@example.test', $this->createVariant(20));
    engine()->run();

    $sequenceFor = fn(string $email) => engine()->getRecoveriesByCustomerId(AbandonedCart::$plugin->getCustomers()->getCustomerByEmail($email)->id)[0]->sequenceId;

    expect($sequenceFor('big@example.test'))->toBe($highValue->id)
        ->and($sequenceFor('small@example.test'))->toBe(AbandonedCart::$plugin->getSequences()->getFallbackSequence()->id);
});

it('only takes carts abandoned after a sequence was turned on', function() {
    $this->turnOn(AbandonedCart::$plugin->getSequences()->getFallbackSequence(), 'now');
    $this->createCart('old.cart@example.test', idleMinutes: 180);

    expect(engine()->run())->toMatchArray(['enrolled' => 0, 'skipped' => 0]);
});

it('credits a recovered order to the email that was sent', function() {
    $this->turnOn(AbandonedCart::$plugin->getSequences()->getFallbackSequence());
    $order = $this->createCart('returning@example.test', $this->createVariant(80));
    engine()->run();

    $this->completeOrder($order);
    $recovery = engine()->getRecoveriesByCustomerId(AbandonedCart::$plugin->getCustomers()->getCustomerByEmail('returning@example.test')->id)[0];

    expect($recovery->status)->toBe(Recovery::STATUS_RECOVERED)
        ->and($recovery->creditedAttemptId)->toBe($recovery->getAttempts()[0]->id)
        ->and($recovery->orderTotal)->toEqual(80.0)
        ->and($recovery->isFirstOrder)->toBeTrue()
        ->and(attemptStatuses($recovery))->toBe([Attempt::STATUS_SENT, Attempt::STATUS_CANCELLED, Attempt::STATUS_CANCELLED]);
});

it('doesn’t credit an order completed before any email went out', function() {
    $sequence = AbandonedCart::$plugin->getSequences()->getFallbackSequence();
    $sequence->abandonedAfterMinutes = 60;
    AbandonedCart::$plugin->getSequences()->saveSequence($sequence);
    $this->turnOn($sequence);

    // Email 1 waits an hour after abandonment, so set a long first delay.
    $first = $sequence->getEmails()[0];
    $first->delayMinutes = 600;
    AbandonedCart::$plugin->getEmails()->saveEmail($first);

    $order = $this->createCart('quick.buyer@example.test', idleMinutes: 90);
    engine()->run();
    $this->completeOrder($order);

    $recovery = engine()->getRecoveriesByCustomerId(AbandonedCart::$plugin->getCustomers()->getCustomerByEmail('quick.buyer@example.test')->id)[0];

    expect($recovery->status)->toBe(Recovery::STATUS_ENDED)
        ->and($recovery->endReason)->toBe(Recovery::END_COMPLETED_BEFORE_EMAIL);
});

it('holds a sequence’s emails while it’s off and sends them when it’s back on', function() {
    $sequences = AbandonedCart::$plugin->getSequences();
    $sequence = $this->turnOn($sequences->getFallbackSequence());
    $first = $sequence->getEmails()[0];
    $first->delayMinutes = 600;
    AbandonedCart::$plugin->getEmails()->saveEmail($first);

    $order = $this->createCart('paused@example.test', idleMinutes: 90);
    $recovery = engine()->enroll($order, new DateTime());

    // Switch off, then let the first email fall due.
    $sequence->enabled = false;
    $sequences->saveSequence($sequence);
    craft\helpers\Db::update('{{%abandonedcart_attempts}}', ['dueAt' => craft\helpers\Db::prepareDateForDb(new DateTime('-5 minutes'))], ['recoveryId' => $recovery->id, 'position' => 1]);

    $held = AbandonedCart::$plugin->getAttempts()->sendDue(new DateTime());

    $sequence->enabled = true;
    $sequences->saveSequence($sequence);
    $released = AbandonedCart::$plugin->getAttempts()->sendDue(new DateTime());

    expect($held['sent'])->toBe(0)
        ->and($released['sent'])->toBe(1);
});

it('stops a sequence when the customer unsubscribes and remembers the choice', function() {
    $this->turnOn(AbandonedCart::$plugin->getSequences()->getFallbackSequence());
    $this->createCart('leaving@example.test');
    engine()->run();

    $customer = AbandonedCart::$plugin->getCustomers()->getCustomerByEmail('leaving@example.test');
    $recovery = engine()->getRecoveriesByCustomerId($customer->id)[0];
    $urls = engine()->getUrls($recovery);
    $token = tokenFrom($urls['unsubscribeUrl']);

    expect(engine()->resolveUnsubscribeToken($token)?->id)->toBe($customer->id)
        ->and(engine()->resolveUnsubscribeToken('nonsense'))->toBeNull();

    AbandonedCart::$plugin->getCustomers()->changeStatus($customer, Customer::STATUS_UNSUBSCRIBED, Customer::SOURCE_UNSUBSCRIBE);
    $recovery = engine()->getRecoveryById($recovery->id);

    expect($recovery->endReason)->toBe(Recovery::END_UNSUBSCRIBED)
        ->and(attemptStatuses($recovery))->toBe([Attempt::STATUS_SENT, Attempt::STATUS_CANCELLED, Attempt::STATUS_CANCELLED]);
});

it('resolves recovery links to the recovery and the email clicked', function() {
    $this->turnOn(AbandonedCart::$plugin->getSequences()->getFallbackSequence());
    $this->createCart('clicker@example.test');
    engine()->run();

    $recovery = engine()->getRecoveriesByCustomerId(AbandonedCart::$plugin->getCustomers()->getCustomerByEmail('clicker@example.test')->id)[0];
    $attempt = $recovery->getAttempts()[0];
    $token = tokenFrom(engine()->getUrls($recovery, $attempt)['recoverUrl']);
    [$resolved, $resolvedAttempt] = engine()->resolveRecoverToken($token);

    expect($resolved->id)->toBe($recovery->id)
        ->and($resolvedAttempt->id)->toBe($attempt->id)
        ->and(engine()->resolveRecoverToken(substr($token, 0, -4) . 'abcd'))->toBeNull();
});

it('moves a recovery under way to the customer’s newer cart, keeping its schedule', function() {
    $this->turnOn(AbandonedCart::$plugin->getSequences()->getFallbackSequence());
    $older = $this->createCart('frequent@example.test', idleMinutes: 300);
    engine()->run();
    $customer = AbandonedCart::$plugin->getCustomers()->getCustomerByEmail('frequent@example.test');
    $original = engine()->getRecoveriesByCustomerId($customer->id)[0];
    $due = array_map(fn(Attempt $attempt) => $attempt->dueAt, $original->getAttempts());
    $before = $this->sentMailCount();

    $newer = $this->createCart('frequent@example.test', idleMinutes: 90);
    $summary = engine()->run();
    engine()->run();

    $recoveries = engine()->getRecoveriesByCustomerId($customer->id);
    $active = array_values(array_filter($recoveries, fn(Recovery $recovery) => $recovery->getIsActive()));
    $ended = array_values(array_filter($recoveries, fn(Recovery $recovery) => $recovery->orderId === $older->id));

    expect($summary['skipped'])->toBe(0)
        ->and($this->sentMailCount())->toBe($before)
        ->and($active)->toHaveCount(1)
        ->and($active[0]->id)->toBe($original->id)
        ->and($active[0]->orderId)->toBe($newer->id)
        ->and(attemptStatuses($active[0]))->toBe([Attempt::STATUS_SENT, Attempt::STATUS_SCHEDULED, Attempt::STATUS_SCHEDULED])
        ->and(array_map(fn(Attempt $attempt) => $attempt->dueAt, $active[0]->getAttempts()))->toEqual($due)
        ->and($ended)->toHaveCount(1)
        ->and($ended[0]->status)->toBe(Recovery::STATUS_ENDED)
        ->and($ended[0]->endReason)->toBe(Recovery::END_NEWER_CART)
        ->and((int)(new craft\db\Query())->from('{{%abandonedcart_skippedcarts}}')->count())->toBe(0);
});

it('applies the contact limit to a new cart after the last recovery has ended', function() {
    $this->turnOn(AbandonedCart::$plugin->getSequences()->getFallbackSequence());
    $this->createCart('frequent@example.test', idleMinutes: 300);
    engine()->run();
    $customer = AbandonedCart::$plugin->getCustomers()->getCustomerByEmail('frequent@example.test');
    engine()->endRecovery(engine()->getRecoveriesByCustomerId($customer->id)[0], Recovery::END_STOPPED);

    $this->createCart('frequent@example.test', idleMinutes: 90);
    engine()->run();

    $reasons = (new craft\db\Query())->select(['reason'])->from('{{%abandonedcart_skippedcarts}}')->column();

    expect($reasons)->toBe([SkippedCart::REASON_CONTACT_LIMIT]);
});

it('ends recoveries once their recovery period is over', function() {
    $sequence = $this->turnOn(AbandonedCart::$plugin->getSequences()->getFallbackSequence(), '-30 days');
    $sequence->recoveryWindowMinutes = 120;
    AbandonedCart::$plugin->getSequences()->saveSequence($sequence);

    $this->createCart('expiring@example.test', idleMinutes: 150);
    engine()->run();
    $ended = engine()->sweep(new DateTime('+1 hour'));
    $recovery = engine()->getRecoveriesByCustomerId(AbandonedCart::$plugin->getCustomers()->getCustomerByEmail('expiring@example.test')->id)[0];

    expect($ended)->toBe(1)
        ->and($recovery->endReason)->toBe(Recovery::END_EXPIRED);
});

it('lets staff retry an email that failed, but nothing else', function() {
    $this->turnOn(AbandonedCart::$plugin->getSequences()->getFallbackSequence());
    $this->createCart('retry.me@example.test', idleMinutes: 90);
    engine()->run();

    $attempts = AbandonedCart::$plugin->getAttempts();
    $recovery = engine()->getRecoveriesByCustomerId(AbandonedCart::$plugin->getCustomers()->getCustomerByEmail('retry.me@example.test')->id)[0];
    [$first, $second] = $recovery->getAttempts();

    expect($attempts->retry($recovery, $first))->toBeNull()
        ->and($attempts->retry($recovery, $second))->toBeNull();

    craft\helpers\Db::update(verbb\abandonedcart\db\Table::ATTEMPTS, ['status' => Attempt::STATUS_FAILED, 'tries' => Attempt::MAX_TRIES, 'sentAt' => null], ['id' => $first->id]);
    $before = $this->sentMailCount();

    expect($attempts->retry($recovery, $attempts->getAttemptById($first->id)))->toBe('sent')
        ->and($attempts->getAttemptById($first->id)->status)->toBe(Attempt::STATUS_SENT)
        ->and($this->sentMailCount())->toBe($before + 1);
});

it('builds recovery and unsubscribe links from URL-safe tokens that resolve', function() {
    $this->turnOn(AbandonedCart::$plugin->getSequences()->getFallbackSequence());
    $this->createCart('link.check@example.test', idleMinutes: 90);
    engine()->run();

    $recovery = engine()->getRecoveriesByCustomerId(AbandonedCart::$plugin->getCustomers()->getCustomerByEmail('link.check@example.test')->id)[0];
    $urls = engine()->getUrls($recovery, $recovery->getAttempts()[0]);
    $recoverToken = tokenFrom($urls['recoverUrl']);
    $unsubscribeToken = tokenFrom($urls['unsubscribeUrl']);

    expect(preg_match('#/recover/([^/?&]+)#', urldecode($urls['recoverUrl']), $raw))->toBe(1)
        ->and($raw[1])->toMatch('/^[A-Za-z0-9_-]+$/')
        ->and(engine()->resolveRecoverToken($recoverToken)[0]->id)->toBe($recovery->id)
        ->and(engine()->resolveUnsubscribeToken($unsubscribeToken)?->id)->toBe($recovery->customerId);
});

it('skips a cart whose new customer a module refuses, and carries on with the rest', function() {
    $this->turnOn(AbandonedCart::$plugin->getSequences()->getFallbackSequence());
    $this->createCart('refused@example.test', idleMinutes: 90);
    $this->createCart('welcome@example.test', idleMinutes: 90);
    $customers = AbandonedCart::$plugin->getCustomers();
    $refuse = function(verbb\abandonedcart\events\CustomerEvent $event) {
        $event->isValid = $event->customer->email !== 'refused@example.test';
    };
    $customers->on(verbb\abandonedcart\services\Customers::EVENT_BEFORE_SAVE_CUSTOMER, $refuse);

    try {
        $summary = engine()->run();
    } finally {
        $customers->off(verbb\abandonedcart\services\Customers::EVENT_BEFORE_SAVE_CUSTOMER, $refuse);
    }

    $skipped = (new craft\db\Query())->from(verbb\abandonedcart\db\Table::SKIPPED_CARTS)->select(['reason', 'customerId'])->all();

    expect($summary)->toMatchArray(['enrolled' => 1, 'skipped' => 1])
        ->and($skipped)->toHaveCount(1)
        ->and($skipped[0]['reason'])->toBe(SkippedCart::REASON_CANCELLED)
        ->and($skipped[0]['customerId'])->toBeNull();
});

it('only undoes an unsubscribe when the customer opts in on the order itself', function() {
    $handle = 'cartConsent' . craft\helpers\StringHelper::randomString(6);
    $field = new craft\fields\Lightswitch(['name' => 'Cart Consent', 'handle' => $handle]);
    expect(Craft::$app->getFields()->saveField($field))->toBeTrue();

    $addToLayout = function(string $type) use ($field) {
        $layout = Craft::$app->getFields()->getLayoutByType($type);
        $tab = new craft\models\FieldLayoutTab(['name' => 'Consent', 'layout' => $layout, 'elements' => [new craft\fieldlayoutelements\CustomField($field, ['uid' => craft\helpers\StringHelper::UUID()])]]);
        $layout->setTabs(array_merge($layout->getTabs(), [$tab]));
        expect(Craft::$app->getFields()->saveLayout($layout, false))->toBeTrue();
    };

    $addToLayout(craft\elements\User::class);
    AbandonedCart::$plugin->getSettings()->consentFieldHandle = $handle;

    $customers = AbandonedCart::$plugin->getCustomers();
    $user = Craft::$app->getUsers()->ensureUserByEmail('opted.in@example.test');
    $user->setFieldValue($handle, true);
    expect(Craft::$app->getElements()->saveElement($user, false))->toBeTrue();
    $customer = $customers->getOrCreateCustomer('opted.in@example.test', $user->id);
    $customers->changeStatus($customer, Customer::STATUS_UNSUBSCRIBED, Customer::SOURCE_UNSUBSCRIBE);

    // Consent saved on the account is older than the unsubscribe, so it doesn't count.
    $this->completeOrder($this->createCart('opted.in@example.test'));

    expect($customers->getCustomerById($customer->id)->status)->toBe(Customer::STATUS_UNSUBSCRIBED);

    $addToLayout(craft\commerce\elements\Order::class);
    $order = $this->createCart('opted.in@example.test');
    $order->setFieldValue($handle, true);
    expect(Craft::$app->getElements()->saveElement($order, false))->toBeTrue();
    $this->completeOrder($order);

    expect($customers->getCustomerById($customer->id)->status)->toBe(Customer::STATUS_ACTIVE);
});

it('ends the recovery and credits the email when the customer orders from another cart', function() {
    $this->turnOn(AbandonedCart::$plugin->getSequences()->getFallbackSequence());
    $left = $this->createCart('two.devices@example.test', $this->createVariant(60), idleMinutes: 90);
    engine()->run();

    $bought = $this->completeOrder($this->createCart('two.devices@example.test', $this->createVariant(40), idleMinutes: 0));
    $recovery = engine()->getRecoveriesByCustomerId(AbandonedCart::$plugin->getCustomers()->getCustomerByEmail('two.devices@example.test')->id)[0];

    expect($recovery->status)->toBe(Recovery::STATUS_RECOVERED)
        ->and($recovery->orderId)->toBe($left->id)
        ->and($recovery->recoveredOrderId)->toBe($bought->id)
        ->and($recovery->getRecoveredOrder()?->id)->toBe($bought->id)
        ->and($recovery->orderTotal)->toEqual(40.0)
        ->and($recovery->creditedAttemptId)->toBe($recovery->getAttempts()[0]->id)
        ->and(attemptStatuses($recovery))->toBe([Attempt::STATUS_SENT, Attempt::STATUS_CANCELLED, Attempt::STATUS_CANCELLED]);
});

it('ends the recovery without credit when the customer orders another cart before any email', function() {
    $sequence = $this->turnOn(AbandonedCart::$plugin->getSequences()->getFallbackSequence());
    $first = $sequence->getEmails()[0];
    $first->delayMinutes = 600;
    AbandonedCart::$plugin->getEmails()->saveEmail($first);

    $this->createCart('early.buyer@example.test', idleMinutes: 90);
    engine()->run();
    $this->completeOrder($this->createCart('early.buyer@example.test', idleMinutes: 0));

    $recovery = engine()->getRecoveriesByCustomerId(AbandonedCart::$plugin->getCustomers()->getCustomerByEmail('early.buyer@example.test')->id)[0];

    expect($recovery->status)->toBe(Recovery::STATUS_ENDED)
        ->and($recovery->endReason)->toBe(Recovery::END_COMPLETED_BEFORE_EMAIL)
        ->and($recovery->recoveredOrderId)->toBeNull()
        ->and(attemptStatuses($recovery))->toBe([Attempt::STATUS_CANCELLED, Attempt::STATUS_CANCELLED, Attempt::STATUS_CANCELLED]);
});

it('credits only a click that restored the cart', function() {
    $this->turnOn(AbandonedCart::$plugin->getSequences()->getFallbackSequence());
    $order = $this->createCart('signed.out@example.test', idleMinutes: 90);
    engine()->run();

    $recovery = engine()->getRecoveriesByCustomerId(AbandonedCart::$plugin->getCustomers()->getCustomerByEmail('signed.out@example.test')->id)[0];
    craft\helpers\Db::update(verbb\abandonedcart\db\Table::ATTEMPTS, ['dueAt' => craft\helpers\Db::prepareDateForDb(new DateTime('-1 minute'))], ['recoveryId' => $recovery->id, 'position' => 2]);
    AbandonedCart::$plugin->getAttempts()->sendDue(new DateTime());
    [$first, $second] = $recovery->getAttempts();

    // Email 1's link restored the cart. Email 2's link only asked the customer to sign in.
    engine()->recordVisit($recovery, $first, 'restored');
    engine()->recordVisit($recovery, $second, 'signInRequired');
    $this->completeOrder($order);
    $recovery = engine()->getRecoveryById($recovery->id);

    expect($second->status)->toBe(Attempt::STATUS_SENT)
        ->and($recovery->creditedAttemptId)->toBe($first->id)
        ->and($recovery->clickedBeforeOrder)->toBeTrue()
        ->and($recovery->recoveredOrderId)->toBe($order->id);
});

it('gives no click credit for a link that didn’t restore the cart', function() {
    $this->turnOn(AbandonedCart::$plugin->getSequences()->getFallbackSequence());
    $order = $this->createCart('expired.link@example.test', idleMinutes: 90);
    engine()->run();

    $recovery = engine()->getRecoveriesByCustomerId(AbandonedCart::$plugin->getCustomers()->getCustomerByEmail('expired.link@example.test')->id)[0];
    engine()->recordVisit($recovery, $recovery->getAttempts()[0], 'signInRequired');
    $this->completeOrder($order);
    $recovery = engine()->getRecoveryById($recovery->id);

    expect($recovery->status)->toBe(Recovery::STATUS_RECOVERED)
        ->and($recovery->creditedAttemptId)->toBe($recovery->getAttempts()[0]->id)
        ->and($recovery->clickedBeforeOrder)->toBeFalse();
});
