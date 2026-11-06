<?php
namespace verbb\abandonedcart\controllers;

use verbb\abandonedcart\AbandonedCart;
use verbb\abandonedcart\models\Customer;

use Craft;
use craft\web\Controller;

use yii\web\Response;

/**
 * Lets a signed-in customer turn cart reminder emails off or on from their account.
 */
class PreferencesController extends Controller
{
    // Public Methods
    // =========================================================================

    public function actionSave(): ?Response
    {
        $this->requirePostRequest();
        $this->requireLogin();

        $user = Craft::$app->getUser()->getIdentity();
        $customers = AbandonedCart::$plugin->getCustomers();
        $customer = $customers->getCustomerByUser($user) ?? $customers->getOrCreateCustomer($user->email, $user->id);
        $wantsEmails = (bool)$this->request->getRequiredBodyParam('emails');

        // Do Not Email is the store's decision, so the customer can't override it here.
        if ($customer->status === Customer::STATUS_DO_NOT_EMAIL) {
            return $this->asModelFailure($customer, Craft::t('abandoned-cart', 'Your cart reminder preference can’t be changed here. Please contact us.'), 'customer');
        }

        $status = $wantsEmails ? Customer::STATUS_ACTIVE : Customer::STATUS_UNSUBSCRIBED;

        if ($customer->status !== $status && !$customers->changeStatus($customer, $status, Customer::SOURCE_ACCOUNT)) {
            return $this->asModelFailure($customer, Craft::t('abandoned-cart', 'Your cart reminder preference couldn’t be saved.'), 'customer');
        }

        return $this->asModelSuccess($customer, $wantsEmails
            ? Craft::t('abandoned-cart', 'We’ll remind you about carts you leave behind.')
            : Craft::t('abandoned-cart', 'We won’t email you about carts you leave behind.'), 'customer');
    }
}
