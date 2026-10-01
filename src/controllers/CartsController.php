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
    // Constants
    // =========================================================================

    private const MAX_PAGE_SIZE = 100;

    private const SORT_FIELDS = [
        'orderId' => 'carts.orderId',
        'email' => 'carts.email',
        'firstReminder' => 'carts.firstReminder',
        'secondReminder' => 'carts.secondReminder',
        'clicked' => 'carts.clicked',
        'dateUpdated' => 'carts.dateUpdated',
    ];

    private const SORT_DIRECTIONS = [
        'asc' => SORT_ASC,
        'desc' => SORT_DESC,
    ];

    // Properties
    // =========================================================================

    protected array|int|bool $allowAnonymous = ['find-carts', 'restore-cart'];


    // Public Methods
    // =========================================================================

    public function actionIndex(): Response
    {
        $this->_requireDashboardAccess();

        return $this->renderTemplate('abandoned-cart/carts');
    }

    public function actionFindCarts(): Response
    {
        $session = Craft::$app->getSession();

        $requestPasskey = $this->request->getParam('passkey');
        $passKey = AbandonedCart::$plugin->getSettings()->getPassKey();

        if (
            !is_string($passKey) ||
            trim($passKey) === '' ||
            !is_string($requestPasskey) ||
            trim($requestPasskey) === '' ||
            !hash_equals($passKey, $requestPasskey)
        ) {
            throw new ForbiddenHttpException(
                'User is not authorized to perform this action, or key mismatch from settings.',
            );
        }

        $abandonedCarts = AbandonedCart::$plugin->getCarts()->getEmailsToSend();

        if ($abandonedCarts) {
            $session->setNotice(Craft::t('abandoned-cart', '{num} abandoned carts were queued.', ['num' => $abandonedCarts]));
        }

        return Craft::$app->controller->redirect(UrlHelper::cpUrl('abandoned-cart'));
    }

    public function actionRestoreCart()
    {
        $session = Craft::$app->getSession();

        $number = $this->request->getParam('number');
        $order = null;

        if (is_string($number) && trim($number) !== '') {
            $order = Order::find()
                ->andWhere(['commerce_orders.number' => $number])
                ->isCompleted(false)
                ->one();
        }

        if (!$order || !AbandonedCart::$plugin->getCarts()->restoreCart($order)) {
            $session->setError(Craft::t('abandoned-cart', "Your cart couldn't be restored, it may have expired."));
        }

        if ($recoveryUrl = AbandonedCart::$plugin->getSettings()->getRecoveryUrl()) {
            return $this->redirect($recoveryUrl);
        }

        return $this->redirect('shop/cart');
    }

    public function actionGetCarts(): Response
    {
        $this->_requireDashboardAccess();
        $this->requireAcceptsJson();

        $page = $this->_getPositiveIntegerParam('page', 1);
        $sort = $this->request->getParam('sort');
        $limit = min($this->_getPositiveIntegerParam('per_page', 10), self::MAX_PAGE_SIZE);
        $search = $this->request->getParam('search');

        if (($page - 1) > intdiv(PHP_INT_MAX, $limit)) {
            throw new BadRequestHttpException('The page parameter is too large.');
        }

        $offset = ($page - 1) * $limit;

        // Ensure that we update any abandoned carts
        $carts = AbandonedCart::$plugin->getCarts()->getAbandonedOrders();

        if (count($carts)) {
            AbandonedCart::$plugin->getCarts()->createNewCarts($carts);
        }

        $query = (new Query())
            ->from(['carts' => '{{%abandonedcart_carts}}'])
            ->select(['*'])
            ->orderBy(['carts.id' => SORT_DESC]);

        if (!AbandonedCart::$plugin->getSettings()->includeBlacklisted) {
            AbandonedCart::$plugin->getCarts()->applyBlacklistToQuery($query);
        }

        if ($search) {
            $likeOperator = Craft::$app->getDb()->getIsPgsql() ? 'ILIKE' : 'LIKE';

            $query->andWhere([
                'or',
                [$likeOperator, '[[email]]', '%' . str_replace(' ', '%', $search) . '%', false],
            ]);
        }

        $total = $query->count();

        $query->limit($limit);
        $query->offset($offset);

        $this->_applySort($query, $sort);

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


    // Private Methods
    // =========================================================================

    private function _requireDashboardAccess(): void
    {
        $this->requireCpRequest();
        $this->requirePermission('accessPlugin-abandoned-cart');
    }

    private function _getPositiveIntegerParam(string $name, int $default): int
    {
        $value = $this->request->getParam($name, $default);

        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (is_string($value) && preg_match('/^[1-9][0-9]*$/D', $value)) {
            $integer = filter_var($value, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1],
            ]);

            if ($integer !== false) {
                return $integer;
            }
        }

        throw new BadRequestHttpException("The $name parameter must be a positive integer.");
    }

    private function _applySort(Query $query, mixed $sort): void
    {
        if (!$sort) {
            return;
        }

        if (!is_array($sort) || !isset($sort[0]) || !is_array($sort[0])) {
            throw new BadRequestHttpException('The sort parameter is invalid.');
        }

        $sortField = $sort[0]['sortField'] ?? null;
        $direction = $sort[0]['direction'] ?? null;

        if (!is_string($sortField) || !isset(self::SORT_FIELDS[$sortField]) || !is_string($direction)) {
            throw new BadRequestHttpException('The sort parameter is invalid.');
        }

        $direction = strtolower($direction);

        if (!isset(self::SORT_DIRECTIONS[$direction])) {
            throw new BadRequestHttpException('The sort parameter is invalid.');
        }

        $query->orderBy([
            self::SORT_FIELDS[$sortField] => self::SORT_DIRECTIONS[$direction],
        ]);
    }
}
