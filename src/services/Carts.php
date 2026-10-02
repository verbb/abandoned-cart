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
use craft\elements\db\ElementQueryInterface;
use craft\helpers\App;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use craft\helpers\Json;
use craft\mail\Message;

use yii\base\Component;
use yii\db\Expression;
use yii\db\IntegrityException;

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

    private const PURGE_BATCH_SIZE = 1000;
    private const QUEUE_DELAY_GRACE_HOURS = 24;

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
        return $this->_saveCart($cart, $runValidation);
    }

    public function getEmailsToSend(): int
    {
        $this->purgeExpiredCarts();

        $carts = $this->getAbandonedOrders();

        if (count($carts)) {
            $this->createNewCarts($carts);
        }

        if ($totalScheduled = $this->scheduleReminders()) {
            return $totalScheduled;
        }

        return 0;
    }

    public function getAbandonedOrders(): array
    {
        $settings = AbandonedCart::$plugin->getSettings();

        // Use Commerce's setting to determine when to classify the start of an abandoned cart.
        // By default, this is orders 1 hour ago
        $dateUpdatedStart = Commerce::getInstance()->getCarts()->getActiveCartEdgeDuration();

        // Then, match any order in the last 24 hours. Any orders older than that aren't deemed abandoned.
        // This is to keep our sample size low.
        $dateUpdatedEnd = new DateTime($dateUpdatedStart);
        $dateUpdatedEnd->sub(new DateInterval("PT24H"));

        $dateUpdatedStart = Db::prepareDateForDb($dateUpdatedStart);
        $dateUpdatedEnd = Db::prepareDateForDb($dateUpdatedEnd);

        $existingOrderIds = (new Query())
            ->select(['orderId'])
            ->from(['{{%abandonedcart_carts}}']);

        $activeRecipients = (new Query())
            ->select([new Expression('LOWER(TRIM([[carts.email]]))')])
            ->from(['carts' => '{{%abandonedcart_carts}}'])
            ->where($this->_recipientBlockingCondition());

        $query = Order::find()
            ->where(['>=', '[[commerce_orders.dateUpdated]]', $dateUpdatedEnd])
            ->andWhere(['<=', '[[commerce_orders.dateUpdated]]', $dateUpdatedStart])
            ->andWhere(['>', 'totalPrice', 0])
            ->andWhere(['=', 'isCompleted', false])
            ->andWhere(['!=', 'email', ''])
            ->andWhere(['not in', '[[commerce_orders.id]]', $existingOrderIds])
            ->andWhere(['not in', new Expression('LOWER(TRIM([[commerce_orders.email]]))'), $activeRecipients])
            ->limit($settings->getMaxEnrollmentsPerRun())
            ->orderBy('commerce_orders.[[dateUpdated]] desc');

        $this->applyBlacklistToQuery($query);

        return $query->all();
    }

    public function scheduleReminders(): int
    {
        // Get all created abandoned carts that haven't been completed. Completed means reminders have already been sent.
        $carts = CartRecord::find()->where(['isScheduled' => 0])->all();

        $firstDelay = AbandonedCart::$plugin->getSettings()->getFirstReminderDelay();
        $secondDelay = AbandonedCart::$plugin->getSettings()->getSecondReminderDelay();

        $firstDelayInSeconds = $firstDelay * 3600;
        $secondDelayInSeconds = $secondDelay * 3600;

        $secondReminderDisabled = AbandonedCart::$plugin->getSettings()->getDisableSecondReminder();

        $i = 0;

        foreach ($carts as $cart) {
            $reminder = null;
            $delay = null;

            if (!$cart->firstReminder) {
                $reminder = 1;
                $delay = $firstDelayInSeconds;
            } elseif (!$cart->secondReminder && !$secondReminderDisabled) {
                $reminder = 2;
                $delay = $secondDelayInSeconds;
            }

            if ($reminder === null || !$this->claimReminderForScheduling($cart->id, $reminder)) {
                continue;
            }

            try {
                Craft::$app->getQueue()->delay($delay)->push(new SendEmailReminder([
                    'cartId' => $cart->id,
                    'reminder' => $reminder,
                ]));
            } catch (Throwable $e) {
                $this->releaseReminderScheduleClaim($cart->id, $reminder);

                throw $e;
            }

            $this->touchReminderScheduleClaim($cart->id, $reminder);

            $i++;
        }

        return $i;
    }

    public function claimReminderForScheduling(int $cartId, int $reminder): bool
    {
        $condition = $this->_reminderClaimCondition($cartId, $reminder, false);

        if ($condition === null) {
            return false;
        }

        $updated = CartRecord::updateAll(['isScheduled' => true], $condition);

        if ($updated) {
            $this->_carts = null;
        }

        return $updated === 1;
    }

    public function releaseReminderScheduleClaim(int $cartId, int $reminder): void
    {
        $condition = $this->_reminderClaimCondition($cartId, $reminder, true, false);

        if ($condition === null) {
            return;
        }

        if (CartRecord::updateAll(['isScheduled' => false], $condition)) {
            $this->_carts = null;
        }
    }

    public function touchReminderScheduleClaim(int $cartId, int $reminder): void
    {
        $condition = $this->_reminderClaimCondition($cartId, $reminder, true);

        if ($condition === null) {
            return;
        }

        if (CartRecord::updateAll(['dateUpdated' => Db::prepareDateForDb(new DateTime())], $condition)) {
            $this->_carts = null;
        }
    }

    public function claimReminderForSending(int $cartId, int $reminder): ?Cart
    {
        if ($reminder !== 1 && $reminder !== 2) {
            return null;
        }

        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            $cart = $this->_getCartForUpdate($cartId);

            if (!$cart || !$this->_canClaimReminderForSending($cart, $reminder)) {
                $transaction->commit();

                return null;
            }

            $prepareForSave = function(Cart $cart) use ($reminder): void {
                $cart->isScheduled = false;
                $cart->firstReminder = true;

                if ($reminder === 2) {
                    $cart->secondReminder = true;
                }
            };

            $prepareForSave($cart);

            if (!$this->_saveCart($cart, true, $prepareForSave)) {
                throw new Exception(Craft::t('abandoned-cart', 'Could not claim abandoned cart reminder.'));
            }

            $claimedCart = $this->_getFreshCartById($cartId);

            $transaction->commit();
            $this->_carts = null;

            return $claimedCart && !$claimedCart->isRecovered ? $claimedCart : null;
        } catch (Throwable $e) {
            if ($transaction->isActive) {
                $transaction->rollBack();
            }

            throw $e;
        }
    }

    public function createNewCarts(array $orders): void
    {
        $settings = AbandonedCart::$plugin->getSettings();
        $maxEnrollments = $settings->getMaxEnrollmentsPerRun();
        $enrollments = 0;

        foreach ($orders as $order) {
            if ($enrollments >= $maxEnrollments) {
                break;
            }

            $existingCart = CartRecord::find()->where(['orderId' => $order->id])->one();

            if ($existingCart) {
                continue;
            }

            $recipientKey = $this->_normalizeRecipient($order->email);

            if ($recipientKey === '') {
                continue;
            }

            // Check if we require at least one previous completed order for privacy
            if ($settings->previousOrderRequired) {
                $previousOrders = Order::find()
                    ->id('not ' . $order->id)
                    ->email($order->email)
                    ->isCompleted()
                    ->count();

                if (!$previousOrders) {
                    continue;
                }
            }

            $recipientIsBlocked = CartRecord::find()
                ->where(['=', new Expression('LOWER(TRIM([[email]]))'), $recipientKey])
                ->andWhere($this->_recipientBlockingCondition())
                ->exists();

            if ($recipientIsBlocked) {
                continue;
            }

            CartRecord::updateAll(['recipientKey' => null], $this->_recipientReleaseCondition($recipientKey));

            $newCart = new CartRecord();
            $newCart->orderId = $order->id;
            $newCart->email = $recipientKey;
            $newCart->recipientKey = $recipientKey;

            try {
                $newCart->save(false);
            } catch (IntegrityException $e) {
                if ($this->_isDuplicateKeyException($e) && (
                    CartRecord::find()->where(['orderId' => $order->id])->exists() ||
                    CartRecord::find()->where(['recipientKey' => $recipientKey])->exists()
                )) {
                    continue;
                }

                throw $e;
            }

            $enrollments++;
        }
    }

    public function purgeExpiredCarts(): int
    {
        $settings = AbandonedCart::$plugin->getSettings();
        $retentionHours = max(
            $settings->getStaleRecordRetentionDays() * 24,
            $settings->getRecipientCooldownHours(),
            (int)$settings->getRestoreExpiryHours(),
            (int)$settings->getFirstReminderDelay() + self::QUEUE_DELAY_GRACE_HOURS,
            $settings->getDisableSecondReminder() ? 0 : (int)$settings->getSecondReminderDelay() + self::QUEUE_DELAY_GRACE_HOURS,
        );

        $cutoff = new DateTime();
        $cutoff->sub(new DateInterval("PT{$retentionHours}H"));

        $expiredIds = CartRecord::find()
            ->select(['id'])
            ->where(['<', 'dateUpdated', Db::prepareDateForDb($cutoff)])
            ->orderBy(['id' => SORT_ASC])
            ->limit(self::PURGE_BATCH_SIZE)
            ->column();

        if (!$expiredIds) {
            return 0;
        }

        return CartRecord::deleteAll(['id' => $expiredIds]);
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

        $subject = AbandonedCart::$plugin->getTemplates()->renderSandboxedString($subject, $renderVariables);
        $templatePath = AbandonedCart::$plugin->getTemplates()->renderSandboxedString($templatePath, $renderVariables);

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

        // Persist the immutable expiry anchor before sending so a delivered link is always recoverable.
        $this->_markRecoveryLinkAsIssued($cart);

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

        $this->_markCartAsSent($cart);

        return true;
    }

    public function markCartAsRecovered(Order $order): void
    {
        if ($cart = $this->getCartByOrderId($order->id)) {
            $this->_updateCartState($cart->id, function(Cart $cart): void {
                $cart->isRecovered = true;
            });
        }
    }

    public function restoreCart(Order $order): bool
    {
        if ($order->isCompleted) {
            return false;
        }

        $cartsService = Commerce::getInstance()->getCarts();
        $session = Craft::$app->getSession();

        if ($cart = $this->getCartByOrderId($order->id)) {
            if ($cart->getIsRecoveryAvailable()) {
                $cartsService->forgetCart();
                $cartsService->setSessionCartNumber($order->number);
                $session->setNotice(Craft::t('abandoned-cart', 'Your cart has been restored.'));

                $this->_updateCartState($cart->id, function(Cart $cart): void {
                    $cart->clicked = true;
                });

                return true;
            }
        }

        return false;
    }

    public function applyBlacklistToQuery(ElementQueryInterface|Query $query): void
    {
        $blacklist = AbandonedCart::$plugin->getSettings()->getBlacklist();

        if (!$blacklist) {
            return;
        }

        $fullEmails = [];
        $domains = [];

        // Split emails and domains so we can filter the query
        foreach ($blacklist as $item) {
            if (str_contains($item, '@')) {
                $fullEmails[] = $item;
            } else {
                $domains[] = $item;
            }
        }

        if ($fullEmails) {
            $query->andWhere(['not in', 'email', $fullEmails]);
        }

        if ($domains) {
            $conditions = ['and'];

            foreach ($domains as $domain) {
                // Using LOWER() for case-insensitive match
                $conditions[] = ['not like', new Expression('LOWER([[email]])'), "@{$domain}"];
            }

            $query->andWhere($conditions);
        }
    }


    // Private Methods
    // =========================================================================

    private function _saveCart(Cart $cart, bool $runValidation, ?callable $prepareForSave = null): bool
    {
        $isNewCart = !$cart->id;

        // Fire a 'beforeSaveCart' event
        if ($this->hasEventHandlers(self::EVENT_BEFORE_SAVE_CART)) {
            $this->trigger(self::EVENT_BEFORE_SAVE_CART, new CartEvent([
                'cart' => $cart,
                'isNew' => $isNewCart,
            ]));
        }

        if ($prepareForSave) {
            $prepareForSave($cart);
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
        $cartRecord->isSent = $cart->isSent;
        $cartRecord->dateLastSent = $cart->dateLastSent;

        if (!$cartRecord->save(false)) {
            return false;
        }

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

    private function _createCartQuery(bool $applyBlacklist = true): Query
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
                'isSent',
                'dateLastSent',
                'dateCreated',
                'dateUpdated',
                'uid',
            ])
            ->from(['{{%abandonedcart_carts}}']);

        if ($applyBlacklist) {
            $this->applyBlacklistToQuery($query);
        }

        return $query;
    }

    private function _getFreshCartById(int $cartId, bool $applyBlacklist = true): ?Cart
    {
        $result = $this->_createCartQuery($applyBlacklist)
            ->andWhere(['id' => $cartId])
            ->one();

        return $result ? new Cart($result) : null;
    }

    private function _getCartForUpdate(int $cartId, bool $applyBlacklist = true): ?Cart
    {
        $command = $this->_createCartQuery($applyBlacklist)
            ->andWhere(['id' => $cartId])
            ->createCommand();
        $params = $command->params;
        $command->setSql($command->getSql() . ' FOR UPDATE');
        $command->bindValues($params);
        $result = $command->queryOne();

        return $result ? new Cart($result) : null;
    }

    private function _canClaimReminderForSending(Cart $cart, int $reminder): bool
    {
        if ($cart->isRecovered || !$cart->isScheduled) {
            return false;
        }

        if ($reminder === 1) {
            return !$cart->firstReminder;
        }

        return $cart->firstReminder && !$cart->secondReminder;
    }

    private function _markCartAsSent(Cart $cart): void
    {
        $this->_updateCartAfterEvent(
            $cart,
            static function(Cart $cart): void {
                $cart->isSent = true;
            },
            Craft::t('abandoned-cart', 'Could not mark abandoned cart email as sent.'),
        );
    }

    private function _markRecoveryLinkAsIssued(Cart $cart): void
    {
        $dateLastSent = new DateTime();

        $this->_updateCartAfterEvent(
            $cart,
            static function(Cart $cart) use ($dateLastSent): void {
                $cart->dateLastSent = $dateLastSent;
            },
            Craft::t('abandoned-cart', 'Could not record the abandoned cart recovery expiry.'),
        );
    }

    private function _updateCartAfterEvent(Cart $cart, callable $prepareForSave, string $error): void
    {
        if (!$cart->id) {
            $prepareForSave($cart);

            if (!$this->_saveCart($cart, true, $prepareForSave)) {
                throw new Exception($error);
            }

            return;
        }

        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            $currentCart = $this->_getCartForUpdate($cart->id, false);

            if (!$currentCart) {
                throw new Exception(Craft::t('abandoned-cart', 'No cart exists with the ID “{id}”.', ['id' => $cart->id]));
            }

            $cart->setAttributes($currentCart->getAttributes(), false);
            $prepareForSave($cart);

            if (!$this->_saveCart($cart, true, $prepareForSave)) {
                throw new Exception($error);
            }

            $transaction->commit();
            $this->_carts = null;
        } catch (Throwable $e) {
            if ($transaction->isActive) {
                $transaction->rollBack();
            }

            throw $e;
        }
    }

    private function _updateCartState(int $cartId, callable $update): bool
    {
        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            $cart = $this->_getCartForUpdate($cartId, false);

            if (!$cart) {
                $transaction->commit();

                return false;
            }

            $update($cart);

            if (!$this->_saveCart($cart, true)) {
                throw new Exception(Craft::t('abandoned-cart', 'Could not update abandoned cart.'));
            }

            $transaction->commit();
            $this->_carts = null;

            return true;
        } catch (Throwable $e) {
            if ($transaction->isActive) {
                $transaction->rollBack();
            }

            throw $e;
        }
    }

    private function _normalizeRecipient(?string $email): string
    {
        return mb_strtolower(trim($email ?? ''));
    }

    private function _isDuplicateKeyException(IntegrityException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? null;
        $driverCode = (int)($exception->errorInfo[1] ?? 0);

        return $sqlState === '23505' || ($sqlState === '23000' && $driverCode === 1062);
    }

    private function _recipientBlockingCondition(): array
    {
        $settings = AbandonedCart::$plugin->getSettings();
        $pendingReminderCondition = ['firstReminder' => false];

        if (!$settings->getDisableSecondReminder()) {
            $pendingReminderCondition = [
                'or',
                $pendingReminderCondition,
                ['secondReminder' => false],
            ];
        }

        return [
            'or',
            ['>=', 'dateCreated', Db::prepareDateForDb($this->_recipientCooldownStart())],
            [
                'and',
                ['isRecovered' => false],
                $pendingReminderCondition,
            ],
        ];
    }

    private function _recipientReleaseCondition(string $recipientKey): array
    {
        $settings = AbandonedCart::$plugin->getSettings();
        $completedReminderCondition = ['firstReminder' => true];

        if (!$settings->getDisableSecondReminder()) {
            $completedReminderCondition = [
                'and',
                $completedReminderCondition,
                ['secondReminder' => true],
            ];
        }

        return [
            'and',
            ['recipientKey' => $recipientKey],
            ['<', 'dateCreated', Db::prepareDateForDb($this->_recipientCooldownStart())],
            [
                'or',
                ['isRecovered' => true],
                $completedReminderCondition,
            ],
        ];
    }

    private function _recipientCooldownStart(): DateTime
    {
        $hours = AbandonedCart::$plugin->getSettings()->getRecipientCooldownHours();
        $date = new DateTime();
        $date->sub(new DateInterval("PT{$hours}H"));

        return $date;
    }

    private function _reminderClaimCondition(int $cartId, int $reminder, bool $isScheduled, bool $requireUnrecovered = true): ?array
    {
        $condition = [
            'id' => $cartId,
            'isScheduled' => $isScheduled,
        ];

        if ($requireUnrecovered) {
            $condition['isRecovered'] = false;
        }

        if ($reminder === 1) {
            $condition['firstReminder'] = false;

            return $condition;
        }

        if ($reminder === 2) {
            $condition['firstReminder'] = true;
            $condition['secondReminder'] = false;

            return $condition;
        }

        return null;
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
