<?php

use verbb\abandonedcart\AbandonedCart;
use verbb\abandonedcart\events\CustomerEvent;
use verbb\abandonedcart\events\CustomerStatusEvent;
use verbb\abandonedcart\models\Customer;
use verbb\abandonedcart\services\Customers;

use craft\elements\User;

uses(verbb\abandonedcart\tests\Integration\TestCase::class);

it('finds one customer per email, whatever its case', function() {
    $customers = AbandonedCart::$plugin->getCustomers();
    $first = $customers->getOrCreateCustomer('  Amelia.Hart@Example.test ');
    $again = $customers->getOrCreateCustomer('amelia.hart@example.test');

    expect($first->email)->toBe('amelia.hart@example.test')
        ->and($again->id)->toBe($first->id)
        ->and($first->getIsEmailable())->toBeTrue()
        ->and($first->getDomain())->toBe('example.test');
});

it('records Do Not Email with who, when and why, and lets a handler cancel it', function() {
    $customers = AbandonedCart::$plugin->getCustomers();
    $customer = $customers->getOrCreateCustomer('buyer@competitor.example');
    $cancel = function(CustomerStatusEvent $event) {
        $event->isValid = $event->note !== 'Blocked by a handler';
    };
    $customers->on(Customers::EVENT_BEFORE_CHANGE_STATUS, $cancel);

    try {
        expect($customers->changeStatus($customer, Customer::STATUS_DO_NOT_EMAIL, Customer::SOURCE_STAFF, 'Blocked by a handler'))->toBeFalse()
            ->and($customers->changeStatus($customer, Customer::STATUS_DO_NOT_EMAIL, Customer::SOURCE_STAFF, 'Competitor test orders'))->toBeTrue();
    } finally {
        $customers->off(Customers::EVENT_BEFORE_CHANGE_STATUS, $cancel);
    }

    $saved = $customers->getCustomerById($customer->id);

    expect($saved->status)->toBe(Customer::STATUS_DO_NOT_EMAIL)
        ->and($saved->statusNote)->toBe('Competitor test orders')
        ->and($saved->statusChangedAt)->not->toBeNull()
        ->and($saved->getIsEmailable())->toBeFalse();
});

it('always applies a customer’s own unsubscribe, even when a handler cancels it', function() {
    $customers = AbandonedCart::$plugin->getCustomers();
    $customer = $customers->getOrCreateCustomer('leaving@example.test');
    $cancelStatus = fn(CustomerStatusEvent $event) => $event->isValid = false;
    $cancelSave = fn(CustomerEvent $event) => $event->isValid = false;
    $customers->on(Customers::EVENT_BEFORE_CHANGE_STATUS, $cancelStatus);
    $customers->on(Customers::EVENT_BEFORE_SAVE_CUSTOMER, $cancelSave);

    try {
        expect($customers->changeStatus($customer, Customer::STATUS_DO_NOT_EMAIL, Customer::SOURCE_STAFF))->toBeFalse()
            ->and($customers->changeStatus($customer, Customer::STATUS_UNSUBSCRIBED, Customer::SOURCE_UNSUBSCRIBE))->toBeTrue();
    } finally {
        $customers->off(Customers::EVENT_BEFORE_CHANGE_STATUS, $cancelStatus);
        $customers->off(Customers::EVENT_BEFORE_SAVE_CUSTOMER, $cancelSave);
    }

    expect($customers->getCustomerById($customer->id)->status)->toBe(Customer::STATUS_UNSUBSCRIBED);
});

it('only lets the customer undo an unsubscribe', function() {
    $customers = AbandonedCart::$plugin->getCustomers();
    $customer = $customers->getOrCreateCustomer('m.oconnor@example.test');
    $customers->changeStatus($customer, Customer::STATUS_UNSUBSCRIBED, Customer::SOURCE_UNSUBSCRIBE);

    expect($customers->changeStatus($customer, Customer::STATUS_ACTIVE, Customer::SOURCE_STAFF))->toBeFalse()
        ->and($customers->changeStatus($customer, Customer::STATUS_ACTIVE, Customer::SOURCE_CHECKOUT))->toBeTrue();
});

it('finds a customer from their user account, by user or by email', function() {
    $customers = AbandonedCart::$plugin->getCustomers();
    $user = new User(['email' => 'account.holder@example.test', 'username' => 'account.holder@example.test']);
    expect(Craft::$app->getElements()->saveElement($user, false))->toBeTrue();

    expect($customers->getCustomerByUser($user))->toBeNull();

    $guest = $customers->getOrCreateCustomer('Account.Holder@example.test');

    expect($customers->getCustomerByUser($user)?->id)->toBe($guest->id);
});


it('lets customers turn reminders back on from their account, but not staff', function() {
    $customers = AbandonedCart::$plugin->getCustomers();
    $customer = $customers->getOrCreateCustomer('self.service@example.test');
    $customers->changeStatus($customer, Customer::STATUS_UNSUBSCRIBED, Customer::SOURCE_ACCOUNT);

    expect($customers->changeStatus($customer, Customer::STATUS_ACTIVE, Customer::SOURCE_STAFF))->toBeFalse()
        ->and($customers->changeStatus($customer, Customer::STATUS_ACTIVE, Customer::SOURCE_ACCOUNT))->toBeTrue()
        ->and($customers->getCustomerById($customer->id)->status)->toBe(Customer::STATUS_ACTIVE);
});

it('keeps an unsubscribed customer unsubscribed when they come off Do Not Email', function() {
    $customers = AbandonedCart::$plugin->getCustomers();
    $customer = $customers->getOrCreateCustomer('opted.out@example.test');
    $customers->changeStatus($customer, Customer::STATUS_UNSUBSCRIBED, Customer::SOURCE_UNSUBSCRIBE);
    $unsubscribedAt = $customers->getCustomerById($customer->id)->statusChangedAt;

    // Both staff routes onto Do Not Email: the bulk action and Add Address, which reuses the existing record.
    expect($customers->changeStatus($customers->getCustomerById($customer->id), Customer::STATUS_DO_NOT_EMAIL, Customer::SOURCE_STAFF, 'Bulk'))->toBeTrue();
    $again = $customers->getOrCreateCustomer('opted.out@example.test');
    expect($customers->changeStatus($again, Customer::STATUS_DO_NOT_EMAIL, Customer::SOURCE_STAFF, 'Added again'))->toBeTrue()
        ->and($customers->getCustomerById($customer->id)->previousStatus['status'] ?? null)->toBe(Customer::STATUS_UNSUBSCRIBED);

    expect($customers->removeDoNotEmail($customers->getCustomerById($customer->id)))->toBeTrue();
    $restored = $customers->getCustomerById($customer->id);

    expect($restored->status)->toBe(Customer::STATUS_UNSUBSCRIBED)
        ->and($restored->statusSource)->toBe(Customer::SOURCE_UNSUBSCRIBE)
        ->and($restored->statusChangedAt)->toEqual($unsubscribedAt)
        ->and($restored->previousStatus)->toBeEmpty()
        ->and($restored->getIsEmailable())->toBeFalse();
});

it('makes a customer emailable again when they come off Do Not Email', function() {
    $customers = AbandonedCart::$plugin->getCustomers();
    $customer = $customers->getOrCreateCustomer('mistake@example.test');
    $customers->changeStatus($customer, Customer::STATUS_DO_NOT_EMAIL, Customer::SOURCE_STAFF);

    expect($customers->removeDoNotEmail($customers->getCustomerById($customer->id)))->toBeTrue()
        ->and($customers->getCustomerById($customer->id)->status)->toBe(Customer::STATUS_ACTIVE)
        ->and($customers->removeDoNotEmail($customers->getCustomerById($customer->id)))->toBeFalse();
});

it('deletes a customer with their cart history, and leaves Commerce alone', function() {
    $this->turnOn(AbandonedCart::$plugin->getSequences()->getFallbackSequence());
    $customers = AbandonedCart::$plugin->getCustomers();
    $order = $this->createCart('erase.me@example.test', idleMinutes: 300);
    AbandonedCart::$plugin->getRecoveries()->run();
    $customer = $customers->getCustomerByEmail('erase.me@example.test');
    $recovery = AbandonedCart::$plugin->getRecoveries()->getRecoveriesByCustomerId($customer->id)[0];
    AbandonedCart::$plugin->getRecoveries()->recordVisit($recovery, $recovery->getAttempts()[0], 'restored');
    AbandonedCart::$plugin->getRecoveries()->skip($this->createCart('erase.me@example.test'), $customer, new DateTime(), verbb\abandonedcart\models\SkippedCart::REASON_CONTACT_LIMIT);
    $this->createCart('keep.me@example.test', idleMinutes: 300);
    AbandonedCart::$plugin->getRecoveries()->run();

    $count = fn(string $table, array $where) => (int)(new craft\db\Query())->from($table)->where($where)->count();

    expect($customers->deleteCustomer($customer))->toBeTrue()
        ->and($customers->getCustomerById($customer->id))->toBeNull()
        ->and($count(verbb\abandonedcart\db\Table::RECOVERIES, ['customerId' => $customer->id]))->toBe(0)
        ->and($count(verbb\abandonedcart\db\Table::ATTEMPTS, ['recoveryId' => $recovery->id]))->toBe(0)
        ->and($count(verbb\abandonedcart\db\Table::VISITS, ['recoveryId' => $recovery->id]))->toBe(0)
        ->and($count(verbb\abandonedcart\db\Table::ACTIVITIES, ['recoveryId' => $recovery->id]))->toBe(0)
        ->and($count(verbb\abandonedcart\db\Table::SKIPPED_CARTS, ['customerId' => $customer->id]))->toBe(0)
        ->and($customers->getCustomerByEmail('keep.me@example.test'))->not->toBeNull()
        ->and(craft\commerce\elements\Order::find()->id($order->id)->status(null)->exists())->toBeTrue();
});

it('lets a handler stop a customer being deleted', function() {
    $customers = AbandonedCart::$plugin->getCustomers();
    $customer = $customers->getOrCreateCustomer('protected@example.test');
    $cancel = fn(CustomerEvent $event) => $event->isValid = false;
    $customers->on(Customers::EVENT_BEFORE_DELETE_CUSTOMER, $cancel);

    try {
        expect($customers->deleteCustomer($customer))->toBeFalse();
    } finally {
        $customers->off(Customers::EVENT_BEFORE_DELETE_CUSTOMER, $cancel);
    }

    expect($customers->getCustomerById($customer->id))->not->toBeNull();
});
