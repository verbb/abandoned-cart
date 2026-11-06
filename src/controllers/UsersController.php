<?php
namespace verbb\abandonedcart\controllers;

use verbb\abandonedcart\AbandonedCart;

use Craft;
use craft\controllers\EditUserTrait;
use craft\web\Controller;
use craft\web\CpScreenResponseBehavior;

use yii\web\Response;

/**
 * The Abandoned Cart screen on a user's account.
 */
class UsersController extends Controller
{
    use EditUserTrait;

    // Constants
    // =========================================================================

    public const SCREEN = 'abandoned-cart';


    // Public Methods
    // =========================================================================

    public function actionIndex(?int $userId = null): Response
    {
        $this->requirePermission('accessPlugin-abandoned-cart');

        $user = $this->editedUser($userId);
        $customer = AbandonedCart::$plugin->getCustomers()->getCustomerByUser($user);

        /** @var Response|CpScreenResponseBehavior $response */
        $response = $this->asEditUserScreen($user, self::SCREEN);
        $response->contentTemplate('abandoned-cart/customers/_user', [
            'customer' => $customer,
            'statusLabels' => CustomersController::statusLabels(),
            ...($customer ? CustomersController::profileVariables($customer) : []),
        ]);

        return $response;
    }
}
