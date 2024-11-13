<?php
namespace verbb\abandonedcart\controllers;

use verbb\abandonedcart\AbandonedCart;
use verbb\abandonedcart\models\Cart;
use verbb\abandonedcart\models\Settings;

use Craft;
use craft\db\Query;
use craft\helpers\AdminTable;
use craft\helpers\ArrayHelper;
use craft\helpers\DateTimeHelper;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use craft\i18n\Locale;
use craft\web\Controller;

use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

use craft\commerce\Plugin as Commerce;
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

    public function actionGetCarts(): Response
    {
        $this->requireAcceptsJson();

        $page = $this->request->getParam('page', 1);
        $sort = $this->request->getParam('sort');
        $limit = $this->request->getParam('per_page', 10);
        $search = $this->request->getParam('search');
        $offset = ($page - 1) * $limit;

        $query = (new Query())
            ->from(['carts' => '{{%abandonedcart_carts}}'])
            ->select(['*'])
            ->orderBy(['id' => SORT_DESC]);

        if ($search) {
            $likeOperator = Craft::$app->getDb()->getIsPgsql() ? 'ILIKE' : 'LIKE';

            $query->andWhere([
                'or',
                [$likeOperator, 'logs.email', '%' . str_replace(' ', '%', $search) . '%', false],
            ]);
        }

        $total = $query->count();

        $query->limit($limit);
        $query->offset($offset);

        if ($sort) {
            $sortField = $sort[0]['sortField'] ?? null;
            $direction = $sort[0]['direction'] ?? null;

            if ($sortField && $direction) {
                $query->orderBy($sortField . ' ' . $direction);
            }
        }

        $carts = $query->all();

        $tableData = [];

        $dateFormat = Craft::$app->getFormattingLocale()->getDateTimeFormat('short', Locale::FORMAT_PHP);

        foreach ($carts as $cartRecord) {
            $cart = new Cart($cartRecord);
            $user = $cart->getUser();
            $order = $cart->getOrder();

            $tableData[] = [
                'email' => $user ? ['title' => $user->email, 'cpEditUrl' => $user->cpEditUrl] : $cart->email,
                'cart' => $order ? ['title' => $order->shortNumber, 'cpEditUrl' => $order->cpEditUrl] : [],
                'total' => $order ? $order->totalPrice : '-',
                'firstReminder' => $cart->firstReminder,
                'secondReminder' => $cart->secondReminder,
                'clicked' => $cart->clicked,
                'status' => $cart->getStatus(),
                'dateUpdated' => $cart->dateUpdated?->format($dateFormat) ?? null,
            ];
        }

        return $this->asJson([
            'pagination' => AdminTable::paginationLinks($page, $total, $limit),
            'data' => $tableData,
        ]);
    }
}
