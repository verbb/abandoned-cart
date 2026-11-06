<?php

use verbb\abandonedcart\AbandonedCart;
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

