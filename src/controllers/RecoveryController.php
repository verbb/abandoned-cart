<?php
namespace verbb\abandonedcart\controllers;

use verbb\abandonedcart\AbandonedCart;
use verbb\abandonedcart\db\Table;
use verbb\abandonedcart\models\Customer;
use verbb\abandonedcart\models\Recovery;

use Craft;
use craft\db\Query;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use craft\web\View;

use DateTime;
use yii\web\Response;

use craft\commerce\elements\Order;
use craft\commerce\Plugin as Commerce;

/**
 * The customer-facing side: recovery links and unsubscribing.
 */
class RecoveryController extends Controller
{
    // Properties
    // =========================================================================

    protected array|int|bool $allowAnonymous = ['restore', 'restore-legacy', 'unsubscribe'];


    // Public Methods
    // =========================================================================

    public function beforeAction($action): bool
    {
        // The signed token authorises unsubscribing, and one-click mail clients post without a CSRF token.
        if ($action->id === 'unsubscribe') {
            $this->enableCsrfValidation = false;
        }

        return parent::beforeAction($action);
    }

    public function actionRestore(string $token): Response
    {
        $resolved = AbandonedCart::$plugin->getRecoveries()->resolveRecoverToken($token);

        if (!$resolved) {
            return $this->_page('notFound', 404);
        }

        [$recovery, $attempt] = $resolved;

        return $this->_restore($recovery, $recovery->getOrder(), $attempt?->getEmail()?->discountCode, $attempt);
    }

    /**
     * Links in emails sent by 4.x keep working until their cart's recovery period would have ended.
     */
    public function actionRestoreLegacy(): Response
    {
        $number = (string)$this->request->getQueryParam('number');
        $order = $number ? Order::find()->number($number)->status(null)->one() : null;

        if (!$order || $order->isCompleted) {
            return $this->_page($order ? 'alreadyCompleted' : 'notFound', $order ? 200 : 404);
        }

        $recovery = $this->_findRecoveryForOrder($order);

        if (!$recovery) {
            return $this->_page('expired', 410);
        }

        return $this->_restore($recovery, $order, $this->request->getQueryParam('couponCode'), null);
    }

    public function actionUnsubscribe(string $token): Response
    {
        $recoveries = AbandonedCart::$plugin->getRecoveries();
        $customer = $recoveries->resolveUnsubscribeToken($token);

        if (!$customer) {
            return $this->_page('notFound', 404);
        }

        // Mail filters open every link in an email, so opening the link only asks; posting the form unsubscribes.
        if (!$this->request->getIsPost()) {
            if ($customer->status === Customer::STATUS_UNSUBSCRIBED) {
                return $this->_page('unsubscribed', 200, ['customer' => $customer]);
            }

            return $this->_page('unsubscribe', 200, ['customer' => $customer, 'token' => $token]);
        }

        if ($customer->status === Customer::STATUS_ACTIVE) {
            AbandonedCart::$plugin->getCustomers()->changeStatus($customer, Customer::STATUS_UNSUBSCRIBED, Customer::SOURCE_UNSUBSCRIBE);
        }

        // One-click unsubscribes from Gmail and Yahoo expect a plain success response.
        if ($this->request->getBodyParam('List-Unsubscribe') === 'One-Click') {
            return $this->asRaw('');
        }

        return $this->_page('unsubscribed', 200, ['customer' => $customer]);
    }


    // Private Methods
    // =========================================================================

    private function _restore(Recovery $recovery, ?Order $order, ?string $couponCode, mixed $attempt): Response
    {
        $recoveries = AbandonedCart::$plugin->getRecoveries();

        if (!$order) {
            $recoveries->recordVisit($recovery, $attempt, 'orderUnavailable');

            return $this->_page('notFound', 404);
        }

        if ($order->isCompleted || $recovery->status === Recovery::STATUS_RECOVERED) {
            $recoveries->recordVisit($recovery, $attempt, 'alreadyCompleted');

            return $this->_page('alreadyCompleted');
        }

        if ($recovery->expiresAt && $recovery->expiresAt < new DateTime()) {
            $recoveries->recordVisit($recovery, $attempt, 'expired');

            return $this->_page('expired', 410);
        }

        if (!$order->getLineItems()) {
            $recoveries->recordVisit($recovery, $attempt, 'cartEmpty');

            return $this->_page('cartEmpty');
        }

        // A cart that belongs to an account only reopens for that account, so a forwarded email can't expose it.
        $owner = $order->getCustomer();
        $user = Craft::$app->getUser();

        if ($owner && $owner->getIsCredentialed() && $user->getId() !== $owner->id) {
            $recoveries->recordVisit($recovery, $attempt, 'signInRequired');
            $user->setReturnUrl($this->request->getAbsoluteUrl());

            return $this->redirect(UrlHelper::siteUrl(Craft::$app->getConfig()->getGeneral()->getLoginPath() ?: 'login'));
        }

        if ($couponCode && $order->couponCode !== $couponCode) {
            $recoveries->applyDiscountCode($order, $couponCode);
        }

        $carts = Commerce::getInstance()->getCarts();
        $carts->forgetCart();
        $carts->setSessionCartNumber($order->number);

        $recoveries->recordVisit($recovery, $attempt, 'restored');
        Craft::$app->getSession()->setNotice(Craft::t('abandoned-cart', 'Welcome back. Your cart is just as you left it.'));

        $returnUrl = $recovery->getSequence()?->getReturnUrl() ?? AbandonedCart::$plugin->getSettings()->getReturnUrl() ?? '';

        return $this->redirect(UrlHelper::isFullUrl($returnUrl) ? $returnUrl : UrlHelper::siteUrl($returnUrl));
    }

    private function _findRecoveryForOrder(Order $order): ?Recovery
    {
        $id = (new Query())
            ->select(['id'])
            ->from([Table::RECOVERIES])
            ->where(['orderId' => $order->id])
            ->orderBy(['abandonedAt' => SORT_DESC])
            ->scalar();

        return $id ? AbandonedCart::$plugin->getRecoveries()->getRecoveryById((int)$id) : null;
    }

    private function _page(string $page, int $status = 200, array $variables = []): Response
    {
        $this->response->setStatusCode($status);

        $site = Craft::$app->getSites()->getCurrentSite();
        $variables += [
            'page' => $page,
            'siteName' => $site->getName(),
            'homeUrl' => $site->getBaseUrl() ?: UrlHelper::siteUrl(),
            'actionUrl' => $this->request->getAbsoluteUrl(),
        ];

        $view = Craft::$app->getView();
        $body = $view->renderTemplate('abandoned-cart/_special/customer/content', $variables, View::TEMPLATE_MODE_CP);
        $layout = AbandonedCart::$plugin->getSettings()->customerPageTemplate;

        // Inside the site's own layout when one is set, so these pages look like the rest of the store.
        if ($layout && $view->doesTemplateExist($layout, View::TEMPLATE_MODE_SITE)) {
            $html = $view->renderString(
                '{% extends layout %}{% block content %}{{ body|raw }}{% endblock %}',
                ['layout' => $layout, 'body' => $body],
                View::TEMPLATE_MODE_SITE,
            );
        } else {
            $html = $view->renderTemplate('abandoned-cart/_special/customer/page', $variables + ['body' => $body], View::TEMPLATE_MODE_CP);
        }

        return $this->asRaw($html);
    }
}
