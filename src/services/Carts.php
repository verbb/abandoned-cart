<?php
namespace verbb\abandonedcart\services;

use verbb\abandonedcart\AbandonedCart;
use verbb\abandonedcart\events\BeforeMailSend;
use verbb\abandonedcart\events\CartEvent;
use verbb\abandonedcart\models\Cart;
use verbb\abandonedcart\models\Settings;
use verbb\abandonedcart\queue\jobs\SendEmailReminder;
use verbb\abandonedcart\records\Cart as CartRecord;

use Craft;
use craft\base\MemoizableArray;
use craft\db\Query;
use craft\helpers\App;
use craft\helpers\ArrayHelper;
use craft\helpers\Json;
use craft\mail\Message;

use yii\base\Component;

use craft\commerce\Plugin as Commerce;
use craft\commerce\elements\Order;

use DateInterval;
use DateTime;
use DateTimeZone;
use Exception;
use Throwable;

class Carts extends Component
{
    // Constants
    // =========================================================================

    public const EVENT_BEFORE_SAVE_CART = 'beforeSaveCart';
    public const EVENT_AFTER_SAVE_CART = 'afterSaveCart';
    public const EVENT_BEFORE_MAIL_SEND = 'beforeMailSend';


    // Properties
    // =========================================================================

    private ?MemoizableArray $_carts = null;


    // Public Methods
    // =========================================================================

    public function getAllCarts(): array
    {
        return $this->_carts()->all();
    }

    public function getCartById(int $id): ?Cart
    {
        return $this->_carts()->firstWhere('id', $id);
    }

    public function getCartByOrderId(int $orderId): ?Cart
    {
        return $this->_carts()->firstWhere('orderId', $orderId);
    }

    public function saveCart(Cart $cart, bool $runValidation = true): bool
    {
        $isNewCart = !$cart->id;

        // Fire a 'beforeSaveCart' event
        if ($this->hasEventHandlers(self::EVENT_BEFORE_SAVE_CART)) {
            $this->trigger(self::EVENT_BEFORE_SAVE_CART, new CartEvent([
                'cart' => $cart,
                'isNew' => $isNewCart,
            ]));
        }

        if ($runValidation && !$cart->validate()) {
            Craft::info('Cart not saved due to validation error.', __METHOD__);
            return false;
        }

        $cartRecord = $this->_getCartRecordById($cart->id);
        $cartRecord->orderId = $cart->orderId;
        $cartRecord->email = $cart->email;
        $cartRecord->clicked = $cart->clicked;
        $cartRecord->isScheduled = $cart->isScheduled;
        $cartRecord->firstReminder = $cart->firstReminder;
        $cartRecord->secondReminder = $cart->secondReminder;
        $cartRecord->isRecovered = $cart->isRecovered;

        $cartRecord->save(false);

        if (!$cart->id) {
            $cart->id = $cartRecord->id;
        }

        // Fire an 'afterSaveCart' event
        if ($this->hasEventHandlers(self::EVENT_AFTER_SAVE_CART)) {
            $this->trigger(self::EVENT_AFTER_SAVE_CART, new CartEvent([
                'cart' => $cart,
                'isNew' => $isNewCart,
            ]));
        }

        return true;
    }

    public function getEmailsToSend(): int
    {
        $carts = $this->getAbandonedOrders();

        if (count($carts)) {
            $this->createNewCarts($carts);
        }

        if ($totalScheduled = $this->scheduleReminders()) {
            return $totalScheduled;
        }

        return 0;
    }

    public function getAbandonedOrders(int $start = 1, int $end = 12): array
    {
        $blacklist = AbandonedCart::$plugin->getSettings()->getBlacklist();
        
        if (!empty($blacklist)) {
            $blacklist = explode(',', $blacklist);
        }

        // Find orders that fit the criteria
        $UTC = new DateTimeZone('UTC');
        $dateUpdatedStart = new DateTime();
        $dateUpdatedStart->setTimezone($UTC);
        $dateUpdatedStart->sub(new DateInterval("PT{$start}H"));

        $dateUpdatedEnd = new DateTime();
        $dateUpdatedEnd->setTimezone($UTC);
        $dateUpdatedEnd->sub(new DateInterval("PT{$end}H"));

        $query = Order::find()
            ->where(['<=', '[[commerce_orders.dateUpdated]]', $dateUpdatedStart->format('Y-m-d H:i:s')])
            ->andWhere(['>=', '[[commerce_orders.dateUpdated]]', $dateUpdatedEnd->format('Y-m-d H:i:s')])
            ->andWhere(['>', 'totalPrice', 0])
            ->andWhere(['=', 'isCompleted', 0])
            ->andWhere(['!=', 'email', ''])
            ->orderBy('commerce_orders.[[dateUpdated]] desc');

        if (is_array($blacklist)) {
            $query->andWhere(['not in', 'email', $blacklist]);
        }

        return $query->all();
    }

    public function scheduleReminders(): int
    {
        // Get all created abandoned carts that havent been completed. Completed being reminders have already been sent
        $carts = CartRecord::find()->where(['isScheduled' => 0])->all();

        $firstDelay = AbandonedCart::$plugin->getSettings()->getFirstReminderDelay();
        $secondDelay = AbandonedCart::$plugin->getSettings()->getSecondReminderDelay();

        $firstDelayInSeconds = $firstDelay * 3600;
        $secondDelayInSeconds = $secondDelay * 3600;

        $secondReminderDisabled = AbandonedCart::$plugin->getSettings()->getDisableSecondReminder();

        $i = 0;

        foreach ($carts as $cart) {
            // if it's the 1st time being scheduled then mark as scheduled
            // and then push it to the queue based on $firstReminderDelay setting
            if (!$cart->firstReminder) {
                Craft::$app->getQueue()->delay($firstDelayInSeconds)->push(new SendEmailReminder([
                    'cartId' => $cart->id,
                    'reminder' => 1,
                ]));

                $cart->isScheduled = true;
                $cart->save(false);

                $i++;
            } else if (!$cart->secondReminder && !$secondReminderDisabled) {
                // if it's the 2nd time being scheduled then mark as scheduled again
                // and then push it to the queue based on $secondReminderDelay setting
                // this wont get triggered if 2nd is disabled via settings
                Craft::$app->getQueue()->delay($secondDelayInSeconds)->push(new SendEmailReminder([
                    'cartId' => $cart->id,
                    'reminder' => 2,
                ]));

                $cart->isScheduled = true;
                $cart->save(false);

                $i++;
            } else {
                // ideally finished carts will be marked as completed/failed and no futher emails will be queued.
            }
        }

        return $i;
    }

    public function createNewCarts(array $orders): void
    {
        foreach ($orders as $order) {
            $existingCart = CartRecord::find()->where(['orderId' => $order->id])->one();
            
            if (!$existingCart) {
                $newCart = new CartRecord();
                $newCart->orderId = $order->id;
                $newCart->email = $order->email;

                $newCart->save(false);
            }
        }
    }

    public function sendMail(Cart $cart, string $subject, ?string $recipient = null, ?string $templatePath = null): bool
    {
        $view = Craft::$app->getView();
        $oldTemplateMode = $view->getTemplateMode();
        $originalLanguage = Craft::$app->language;

        if (str_starts_with($templatePath, 'abandoned-cart/emails')) {
            $view->setTemplateMode($view::TEMPLATE_MODE_CP);
        } else {
            $view->setTemplateMode($view::TEMPLATE_MODE_SITE);
        }

        $order = $cart->getOrder();

        if (!$order) {
            $error = Craft::t('abandoned-cart', 'Could not find Order for Abandoned Cart email.');

            AbandonedCart::error($error);

            Craft::$app->language = $originalLanguage;

            $view->setTemplateMode($oldTemplateMode);

            return false;
        }

        if (!$order->hasLineItems()) {
            $warning = Craft::t('abandoned-cart', 'Skipped Abandoned Cart email, Order doesn‘t have Line Items.');
            
            AbandonedCart::info($warning);

            Craft::$app->language = $originalLanguage;
            
            $view->setTemplateMode($oldTemplateMode);
            
            return false;
        }
        
        Craft::$app->language = $order->orderLanguage;

        $checkoutLink = 'abandoned-cart-restore?number=' . $order->number;

        $discount = AbandonedCart::$plugin->getSettings()->getDiscountCode();
        
        if ($discount) {
            $discountCode = $discount;
            $checkoutLink = $checkoutLink . '&couponCode=' . $discountCode;
        } else {
            $discountCode = false;
        }

        $renderVariables = [
            'order' => $order,
            'discount' => $discountCode,
            'currentSite' => $order->orderSite,
            'checkoutLink' => $checkoutLink,
        ];

        $subject = $view->renderString($subject, $renderVariables);
        $templatePath = $view->renderString($templatePath, $renderVariables);

        if (!$view->doesTemplateExist($templatePath)) {
            $error = Craft::t('abandoned-cart', 'Email template does not exist at “{templatePath}”.', [
                'templatePath' => $templatePath,
            ]);

            AbandonedCart::error($error);

            Craft::$app->language = $originalLanguage;
            
            $view->setTemplateMode($oldTemplateMode);
            
            return false;
        }

        $emailBody = $view->renderTemplate($templatePath, $renderVariables);

        $settings = Craft::$app->projectConfig->get('email');

        $newEmail = Craft::$app->getMailer()->compose();
        $newEmail->setFrom([App::parseEnv($settings['fromEmail']) => App::parseEnv($settings['fromName'])]);
        $newEmail->setTo($recipient);
        $newEmail->setSubject($subject);
        $newEmail->setHtmlBody($emailBody);

        $event = new BeforeMailSend([
            'order' => $order,
            'message' => $newEmail,
        ]);

        $newEmail = $event->message;

        $this->trigger(self::EVENT_BEFORE_MAIL_SEND, $event);

        if (!$event->isValid) {
            return false;
        }

        try {
            if (!$newEmail->send()) {
                $error = Craft::t('abandoned-cart', 'Abandoned cart email “{email}” could not be sent for order “{order}”.', [
                    'order' => $order->id,
                ]);

                AbandonedCart::error($error);

                Craft::$app->language = $originalLanguage;

                $view->setTemplateMode($oldTemplateMode);

                return false;
            }
        } catch (Throwable $e) {
            $error = Craft::t('abandoned-cart', 'Abandoned cart email could not be sent for order “{order}”. Error: {error} {file}:{line}', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'order' => $order->id,
            ]);

            AbandonedCart::error($error);

            Craft::$app->language = $originalLanguage;

            $view->setTemplateMode($oldTemplateMode);

            return false;
        }

        return true;
    }

    public function markCartAsRecovered(Order $order): void
    {
        if ($cart = $this->getCartByOrderId($order->id)) {
            $cart->isRecovered = true;

            $this->saveCart($cart);
        }
    }

    public function restoreCart(Order $order): void
    {
        if ($cart = $this->getCartByOrderId($order->id)) {
            $expiry = AbandonedCart::$plugin->getSettings()->getRestoreExpiryHours();

            $expiredTime = $cart->dateUpdated;
            $expiredTime->add(new DateInterval("PT{$expiry}H"));
            $expiredTimestamp = $expiredTime->getTimestamp();

            $now = new DateTime();
            $nowTimestamp = $now->getTimestamp();

            if ($nowTimestamp < $expiredTimestamp) {
                Commerce::getInstance()->getCarts()->forgetCart();
                $session->set('commerce_cart', $number);
                $session->setNotice(Craft::t('abandoned-cart', 'Your cart has been restored.'));

                $cart->clicked = true;

                $this->saveCart($cart);

                return true;
            }
        }

        return false;
    }


    // Private Methods
    // =========================================================================

    private function _carts(): MemoizableArray
    {
        if (!isset($this->_carts)) {
            $this->_carts = new MemoizableArray(
                $this->_createCartQuery()->all(),
                fn(array $result) => new Cart($result),
            );
        }

        return $this->_carts;
    }

    private function _createCartQuery(): Query
    {
        $query = (new Query())
            ->select([
                'id',
                'orderId',
                'email',
                'clicked',
                'isScheduled',
                'firstReminder',
                'secondReminder',
                'isRecovered',
                'dateCreated',
                'dateUpdated',
                'uid',
            ])
            ->from(['{{%abandonedcart_carts}}']);

        if ($blacklist = AbandonedCart::$plugin->getSettings()->getBlacklist()) {
            $blacklist = explode(',', $blacklist);

            $query->where(['not in', 'email', $blacklist]);
        }

        return $query;
    }

    private function _getCartRecordById(int $cartId = null): ?CartRecord
    {
        if ($cartId !== null) {
            $cartRecord = CartRecord::findOne(['id' => $cartId]);

            if (!$cartRecord) {
                throw new Exception(Craft::t('abandoned-cart', 'No cart exists with the ID “{id}”.', ['id' => $cartId]));
            }
        } else {
            $cartRecord = new CartRecord();
        }

        return $cartRecord;
    }

}
