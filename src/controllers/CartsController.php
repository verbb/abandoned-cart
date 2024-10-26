<?php
namespace verbb\abandonedcart\controllers;

use verbb\abandonedcart\AbandonedCart;
use verbb\abandonedcart\models\Log;
use verbb\abandonedcart\models\Settings;

use Craft;
use craft\helpers\ArrayHelper;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use craft\web\Controller;

use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

use craft\commerce\elements\Order;

class CartsController extends Controller
{
    // Properties
    // =========================================================================

    protected array|int|bool $allowAnonymous = ['find-carts', 'restore-cart'];


    // Public Methods
    // =========================================================================

    public function actionIndex(): Response
    {
        $carts = AbandonedCart::$plugin->getCarts()->getAllCarts();

        return $this->renderTemplate('abandoned-cart/carts', [
            'carts' => $carts,
        ]);
    }

    public function actionFindCarts(): Response
    {
        $requestPasskey = $this->request->getParam('passkey');
        $passKey = AbandonedCart::$plugin->getSettings()->getPassKey();

        if ($requestPasskey === $passKey) {
            $abandonedCarts = AbandonedCart::$plugin->getCarts()->getEmailsToSend();

            if ($abandonedCarts) {
                Craft::$app->getSession()->setNotice(Craft::t('abandoned-cart', '{num} abandoned carts were queued.', ['num' => $abandonedCarts]));
            }
            
            return Craft::$app->controller->redirect(UrlHelper::cpUrl('abandoned-cart'));
        }

        throw new ForbiddenHttpException('User is not authorized to perform this action, or key mismatch from settings.');
    }

    public function actionRestoreCart()
    {
        $number = $this->request->getParam('number');
        $order = Order::find()->number($number)->one();

        if ($order && !$order->isCompleted){
            if (!AbandonedCart::$plugin->getCarts()->restoreCart($order)) {
                $session->setNotice(Craft::t('abandoned-cart', "Your cart couldn't be restored, it may have expired."));
            }
        }
        
        if ($recoveryUrl = AbandonedCart::$plugin->getSettings()->getRecoveryUrl()) {
            return $this->redirect($recoveryUrl);
        }
        
        return $this->redirect('shop/cart');
    }
}
