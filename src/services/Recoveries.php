<?php
namespace verbb\abandonedcart\services;

use verbb\abandonedcart\AbandonedCart;
use verbb\abandonedcart\db\Table;
use verbb\abandonedcart\events\ConsentEvent;
use verbb\abandonedcart\events\DefineUrlsEvent;
use verbb\abandonedcart\events\EnrollmentEvent;
use verbb\abandonedcart\events\RecoveryEvent;
use verbb\abandonedcart\events\SkippedCartEvent;
use verbb\abandonedcart\models\Attempt;
use verbb\abandonedcart\models\Customer;
use verbb\abandonedcart\models\Recovery;
use verbb\abandonedcart\models\Sequence;
use verbb\abandonedcart\models\SkippedCart;
use verbb\abandonedcart\queue\jobs\ProcessRecoveries;
use verbb\abandonedcart\records\Recovery as RecoveryRecord;
use verbb\abandonedcart\records\SkippedCart as SkippedCartRecord;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\elements\User;
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;
use craft\services\Gc;
use craft\web\Application as WebApplication;

use DateInterval;
use DateTime;
use InvalidArgumentException;
use yii\base\InvalidArgumentException as YiiInvalidArgumentException;
use Throwable;
use yii\base\Event;

use craft\commerce\elements\Order;

class Recoveries extends Component
{
    // Constants
    // =========================================================================

    public const EVENT_BEFORE_ENROLL = 'beforeEnroll';
    public const EVENT_AFTER_ENROLL = 'afterEnroll';
    public const EVENT_AFTER_SKIP = 'afterSkip';
    public const EVENT_AFTER_RECOVER = 'afterRecover';
    public const EVENT_AFTER_END = 'afterEnd';
    public const EVENT_RESOLVE_CONSENT = 'resolveConsent';
    public const EVENT_DEFINE_URLS = 'defineUrls';

    // On sites without a cron job, a control panel or site request queues a run this often.
    public const AUTO_RUN_INTERVAL_SECONDS = 300;


    // Public Methods
    // =========================================================================

    public function registerEventHandlers(): void
    {
        Event::on(Order::class, Order::EVENT_AFTER_COMPLETE_ORDER, function(Event $event) {
            try {
                $this->handleCompletedOrder($event->sender);
            } catch (Throwable $e) {
                // Checkout must never fail because of reporting.
                Craft::error('Abandoned Cart couldn’t record a completed order: ' . $e->getMessage(), __METHOD__);
            }
        });

        Event::on(Gc::class, Gc::EVENT_RUN, function() {
            $this->purgeHistory();
        });

        if (Craft::$app instanceof WebApplication) {
            Craft::$app->on(WebApplication::EVENT_AFTER_REQUEST, function() {
                $this->queueRunIfDue();
            });
        }
    }

    public function getRecoveryById(int $id): ?Recovery
    {
        $row = $this->_createRecoveryQuery()->where(['id' => $id])->one();

        return $row ? new Recovery($row) : null;
    }

    public function getRecoveryByUid(string $uid): ?Recovery
    {
        $row = $this->_createRecoveryQuery()->where(['uid' => $uid])->one();

        return $row ? new Recovery($row) : null;
    }

    public function getRecoveriesByCustomerId(int $customerId): array
    {
        $rows = $this->_createRecoveryQuery()->where(['customerId' => $customerId])->orderBy(['abandonedAt' => SORT_DESC])->all();

        return array_map(static fn(array $row): Recovery => new Recovery($row), $rows);
    }

    public function getSkippedCartById(int $id): ?SkippedCart
    {
        $row = (new Query())->from([Table::SKIPPED_CARTS])->where(['id' => $id])->one();

        return $row ? new SkippedCart($row) : null;
    }

    /**
     * One scheduler pass: end what's expired, enrol carts that have gone idle, then send what's due.
     */
    /**
     * @param string $trigger What started the run, one of the `Runs::TRIGGER_*` constants, for System Status.
     */
    public function run(?DateTime $now = null, string $trigger = Runs::TRIGGER_OTHER): array
    {
        $now ??= new DateTime();
        $started = microtime(true);
        $mutex = Craft::$app->getMutex();
        $lock = 'abandoned-cart:run';

        if (!$mutex->acquire($lock, 5)) {
            return ['locked' => true];
        }

        try {
            $summary = [
                'ended' => $this->sweep($now),
                'enrolled' => 0,
                'skipped' => 0,
            ];

            foreach ($this->discover($now) as $order) {
                $result = $this->enroll($order, $now);

                if ($result instanceof Recovery) {
                    $summary['enrolled']++;
                } elseif ($result instanceof SkippedCart) {
                    $summary['skipped']++;
                }
            }

            $summary += AbandonedCart::$plugin->getAttempts()->sendDue($now);

            $state = AbandonedCart::$plugin->getState();
            $state->set(State::LAST_RUN_AT, $now->format(DATE_ATOM));
            $state->set(State::LAST_RUN_SUMMARY, $summary);
            AbandonedCart::$plugin->getRuns()->record($trigger, $now, (int)round((microtime(true) - $started) * 1000), $summary);

            // The command line's PHP is what a cron job needs, and a web request can't see it.
            if ($trigger === Runs::TRIGGER_CONSOLE && PHP_BINARY && $state->get(State::CLI_PHP_BINARY) !== PHP_BINARY) {
                $state->set(State::CLI_PHP_BINARY, PHP_BINARY);
            }

            return $summary;
        } finally {
            $mutex->release($lock);
        }
    }

    public function queueRunIfDue(): void
    {
        try {
            $state = AbandonedCart::$plugin->getState();
            $last = max(strtotime((string)$state->get(State::LAST_RUN_AT, '')) ?: 0, strtotime((string)$state->get(State::LAST_QUEUED_AT, '')) ?: 0);

            if (time() - $last < self::AUTO_RUN_INTERVAL_SECONDS || !AbandonedCart::$plugin->getSequences()->getEnabledSequences()) {
                return;
            }

            $state->set(State::LAST_QUEUED_AT, (new DateTime())->format(DATE_ATOM));
            Craft::$app->getQueue()->push(new ProcessRecoveries());
        } catch (Throwable $e) {
            Craft::warning('Abandoned Cart couldn’t queue a scheduler run: ' . $e->getMessage(), __METHOD__);
        }
    }

    /**
     * Carts that have been idle long enough for at least one switched-on sequence and haven't been handled for this
     * abandonment yet. Carts that changed while in recovery move their remaining emails later instead.
     *
     * @return Order[]
     */
    public function discover(DateTime $now): array
    {
        $sequences = AbandonedCart::$plugin->getSequences()->getEnabledSequences();

        if (!$sequences) {
            return [];
        }

        $this->rescheduleChangedCarts();

        $minAbandon = min(array_map(static fn(Sequence $sequence): int => $sequence->getAbandonedAfterMinutes(), $sequences));
        $maxReach = max(array_map(static fn(Sequence $sequence): int => $sequence->getAbandonedAfterMinutes() + $sequence->getRecoveryWindowMinutes(), $sequences));
        $latest = (clone $now)->sub(new DateInterval('PT' . $minAbandon . 'M'));
        $earliest = (clone $now)->sub(new DateInterval('PT' . $maxReach . 'M'));

        // Nothing switched on before this point can take a cart, so older carts aren't worth reading.
        $starts = array_filter(array_map(static fn(Sequence $sequence): ?DateTime => $sequence->enrollmentStartedAt, $sequences));

        if ($starts && count($starts) === count($sequences)) {
            $earliestStart = min($starts);
            $earliestStart = (clone $earliestStart)->sub(new DateInterval('PT' . $minAbandon . 'M'));
            $earliest = max($earliest, $earliestStart);
        }

        $handled = static fn(string $table): Query => (new Query())
            ->from(['h' => $table])
            ->where('[[h.orderId]] = [[elements.id]]')
            ->andWhere('[[h.abandonedAt]] >= [[elements.dateUpdated]]');

        return Order::find()
            ->isCompleted(false)
            ->hasLineItems(true)
            ->email(':notempty:')
            ->dateUpdated(['and', '<= ' . $latest->format(DATE_ATOM), '>= ' . $earliest->format(DATE_ATOM)])
            ->andWhere(['not exists', $handled(Table::RECOVERIES)])
            ->andWhere(['not exists', $handled(Table::SKIPPED_CARTS)])
            ->orderBy(['elements.dateUpdated' => SORT_ASC])
            ->limit(AbandonedCart::$plugin->getSettings()->maxEnrollmentsPerRun)
            ->status(null)
            ->all();
    }

    /**
     * Enrols a cart in the first sequence it matches, or records why it was skipped. Returns null when the cart
     * matches a sequence that isn't ready for it yet, so it's looked at again next run.
     */
    public function enroll(Order $order, DateTime $now): Recovery|SkippedCart|null
    {
        $abandonedAt = DateTimeHelper::toDateTime($order->dateUpdated) ?: $now;

        try {
            $customer = AbandonedCart::$plugin->getCustomers()->getOrCreateCustomer((string)$order->getEmail(), $order->getCustomerId());
        } catch (YiiInvalidArgumentException $e) {
            // A plugin or module refused the new customer, so this cart is left out rather than stopping the run.
            return $this->skip($order, null, $abandonedAt, SkippedCart::REASON_CANCELLED, $e->getMessage());
        }

        $settings = AbandonedCart::$plugin->getSettings();

        if ($customer->status === Customer::STATUS_DO_NOT_EMAIL) {
            return $this->skip($order, $customer, $abandonedAt, SkippedCart::REASON_DO_NOT_EMAIL);
        }

        if ($customer->status === Customer::STATUS_UNSUBSCRIBED) {
            return $this->skip($order, $customer, $abandonedAt, SkippedCart::REASON_UNSUBSCRIBED);
        }

        if (in_array($customer->getDomain(), $settings->getBlockedDomains(), true)) {
            return $this->skip($order, $customer, $abandonedAt, SkippedCart::REASON_BLOCKED_DOMAIN, $customer->getDomain());
        }

        if (!$this->hasConsent($order, $customer)) {
            return $this->skip($order, $customer, $abandonedAt, SkippedCart::REASON_NO_CONSENT);
        }

        if ($settings->returningCustomersOnly && AbandonedCart::$plugin->getAudiences()->countPreviousOrders($order) === 0) {
            return $this->skip($order, $customer, $abandonedAt, SkippedCart::REASON_NOT_RETURNING);
        }

        $sequence = null;

        foreach (AbandonedCart::$plugin->getSequences()->getEnabledSequences() as $candidate) {
            if (!AbandonedCart::$plugin->getAudiences()->matches($candidate, $order)) {
                continue;
            }

            // A cart counts as abandoned once it's been idle for Abandoned After. Carts that reached that point before a
            // sequence was switched on carry on to the next one, as if it weren't live yet.
            $abandonedFrom = (clone $abandonedAt)->add(new DateInterval('PT' . $candidate->getAbandonedAfterMinutes() . 'M'));

            if ($candidate->enrollmentStartedAt && $abandonedFrom < $candidate->enrollmentStartedAt) {
                continue;
            }

            $idleMinutes = ($now->getTimestamp() - $abandonedAt->getTimestamp()) / 60;

            if ($idleMinutes < $candidate->getAbandonedAfterMinutes()) {
                return null;
            }

            $sequence = $candidate;

            break;
        }

        if (!$sequence) {
            return $this->skip($order, $customer, $abandonedAt, SkippedCart::REASON_NO_SEQUENCE);
        }

        if ($this->_wasContactedRecently($customer, $sequence, $now, $order)) {
            return $this->skip($order, $customer, $abandonedAt, SkippedCart::REASON_CONTACT_LIMIT);
        }

        if ($this->hasEventHandlers(self::EVENT_BEFORE_ENROLL)) {
            $event = new EnrollmentEvent(['order' => $order, 'customer' => $customer, 'sequence' => $sequence]);
            $this->trigger(self::EVENT_BEFORE_ENROLL, $event);

            if (!$event->isValid) {
                return $this->skip($order, $customer, $abandonedAt, SkippedCart::REASON_CANCELLED, $event->skipReason);
            }
        }

        $transaction = Craft::$app->getDb()->beginTransaction();

        try {
            // A customer only follows one cart at a time, so a newer cart replaces an older one still in recovery.
            foreach ($this->_getActiveRecoveriesForCustomer($customer->id) as $older) {
                if ($older->orderId !== $order->id) {
                    $this->endRecovery($older, Recovery::END_NEWER_CART);
                }
            }

            $versionId = AbandonedCart::$plugin->getSequences()->getCurrentVersionId($sequence);
            $snapshot = Json::decode((string)(new Query())->select(['snapshot'])->from([Table::SEQUENCE_VERSIONS])->where(['id' => $versionId])->scalar());

            $record = new RecoveryRecord();
            $record->orderId = $order->id;
            $record->customerId = $customer->id;
            $record->siteId = $order->orderSiteId;
            $record->sequenceId = $sequence->id;
            $record->sequenceVersionId = $versionId;
            $record->status = Recovery::STATUS_ACTIVE;
            $record->cartTotal = (float)$order->getTotal();
            $record->currency = $order->currency;
            $record->attributionWindowMinutes = $sequence->getAttributionWindowMinutes();
            $record->abandonedAt = Db::prepareDateForDb($abandonedAt);
            $record->expiresAt = Db::prepareDateForDb((clone $abandonedAt)->add(new DateInterval('PT' . ($sequence->getAbandonedAfterMinutes() + $sequence->getRecoveryWindowMinutes()) . 'M')));
            $record->save(false);

            $recovery = $this->getRecoveryById($record->id);

            AbandonedCart::$plugin->getAttempts()->scheduleAttempts($recovery, $snapshot['emails'] ?? [], $sequence->getAbandonedAfterMinutes());
            AbandonedCart::$plugin->getActivities()->log($recovery->id, Activities::TYPE_ENROLLED, null, ['sequence' => $sequence->name]);

            $transaction->commit();
        } catch (Throwable $e) {
            $transaction->rollBack();

            throw $e;
        }

        if ($this->hasEventHandlers(self::EVENT_AFTER_ENROLL)) {
            $this->trigger(self::EVENT_AFTER_ENROLL, new RecoveryEvent(['recovery' => $recovery]));
        }

        return $recovery;
    }

    public function skip(Order $order, ?Customer $customer, DateTime $abandonedAt, string $reason, ?string $detail = null): SkippedCart
    {
        $record = new SkippedCartRecord();
        $record->orderId = $order->id;
        $record->customerId = $customer?->id;
        $record->siteId = $order->orderSiteId;
        $record->reason = $reason;
        $record->detail = $detail ? mb_substr($detail, 0, 255) : null;
        $record->cartTotal = (float)$order->getTotal();
        $record->currency = $order->currency;
        $record->abandonedAt = Db::prepareDateForDb($abandonedAt);
        $record->save(false);

        $skipped = $this->getSkippedCartById($record->id);

        if ($this->hasEventHandlers(self::EVENT_AFTER_SKIP)) {
            $this->trigger(self::EVENT_AFTER_SKIP, new SkippedCartEvent(['skippedCart' => $skipped]));
        }

        return $skipped;
    }

    public function hasConsent(Order $order, Customer $customer): bool
    {
        $handle = AbandonedCart::$plugin->getSettings()->consentFieldHandle;
        $consent = true;

        if ($handle) {
            $consent = $this->_readConsent($order, $handle) ?? $this->_readConsent($order->getCustomer(), $handle) ?? false;
        }

        if ($this->hasEventHandlers(self::EVENT_RESOLVE_CONSENT)) {
            $event = new ConsentEvent(['order' => $order, 'customer' => $customer, 'hasConsent' => $consent]);
            $this->trigger(self::EVENT_RESOLVE_CONSENT, $event);
            $consent = $event->hasConsent;
        }

        return $consent;
    }

    /**
     * Credits a completed order to the email that earned it: the last one clicked, or the last one sent, as long as
     * the order came within the attribution window of that email.
     */
    public function handleCompletedOrder(Order $order): void
    {
        $customers = AbandonedCart::$plugin->getCustomers();
        $customer = $order->getEmail() ? $customers->getCustomerByEmail($order->getEmail()) : null;

        // Opting in at checkout is the customer's own choice, so it undoes an earlier unsubscribe.
        if ($customer && $customer->status === Customer::STATUS_UNSUBSCRIBED && AbandonedCart::$plugin->getSettings()->consentFieldHandle && $this->hasConsent($order, $customer)) {
            $customers->changeStatus($customer, Customer::STATUS_ACTIVE, Customer::SOURCE_CHECKOUT);
        }

        $row = $this->_createRecoveryQuery()
            ->where(['orderId' => $order->id])
            ->andWhere(['not', ['status' => Recovery::STATUS_RECOVERED]])
            ->orderBy(['abandonedAt' => SORT_DESC])
            ->one();

        if (!$row) {
            return;
        }

        $recovery = new Recovery($row);
        $attempts = AbandonedCart::$plugin->getAttempts()->getAttemptsByRecoveryId($recovery->id);
        $sent = array_values(array_filter($attempts, static fn(Attempt $attempt): bool => $attempt->status === Attempt::STATUS_SENT));
        $orderedAt = DateTimeHelper::toDateTime($order->dateOrdered) ?: new DateTime();

        if (!$sent) {
            if ($recovery->getIsActive()) {
                $this->endRecovery($recovery, Recovery::END_COMPLETED_BEFORE_EMAIL);
            }

            return;
        }

        $lastSent = end($sent);
        $windowEnds = (clone $lastSent->sentAt)->add(new DateInterval('PT' . $recovery->attributionWindowMinutes . 'M'));

        if ($orderedAt > $windowEnds) {
            if ($recovery->getIsActive()) {
                $this->endRecovery($recovery, Recovery::END_COMPLETED_OUTSIDE_WINDOW);
            }

            return;
        }

        $clickedAttemptId = (new Query())
            ->select(['attemptId'])
            ->from([Table::VISITS])
            ->where(['recoveryId' => $recovery->id])
            ->andWhere(['not', ['attemptId' => null]])
            ->orderBy(['dateCreated' => SORT_DESC, 'id' => SORT_DESC])
            ->scalar();
        $creditedId = $clickedAttemptId ? (int)$clickedAttemptId : $lastSent->id;
        $credited = AbandonedCart::$plugin->getAttempts()->getAttemptById($creditedId);

        Db::update(Table::RECOVERIES, [
            'status' => Recovery::STATUS_RECOVERED,
            'recoveredAt' => Db::prepareDateForDb($orderedAt),
            'creditedAttemptId' => $creditedId,
            'clickedBeforeOrder' => (bool)$clickedAttemptId,
            'orderTotal' => (float)$order->getTotal(),
            'isFirstOrder' => AbandonedCart::$plugin->getAudiences()->countPreviousOrders($order) === 0,
            'couponCode' => $order->couponCode,
            'endReason' => null,
            'endedAt' => null,
        ], ['id' => $recovery->id]);

        AbandonedCart::$plugin->getAttempts()->cancelScheduled($recovery, Craft::t('abandoned-cart', 'the order was completed'));
        AbandonedCart::$plugin->getActivities()->log($recovery->id, Activities::TYPE_RECOVERED, $creditedId, ['number' => $order->reference ?: $order->getShortNumber(), 'position' => $credited?->position]);

        if ($this->hasEventHandlers(self::EVENT_AFTER_RECOVER)) {
            $this->trigger(self::EVENT_AFTER_RECOVER, new RecoveryEvent(['recovery' => $this->getRecoveryById($recovery->id)]));
        }
    }

    public function endRecovery(Recovery $recovery, string $reason): void
    {
        if (!$recovery->getIsActive()) {
            return;
        }

        Db::update(Table::RECOVERIES, [
            'status' => Recovery::STATUS_ENDED,
            'endReason' => $reason,
            'endedAt' => Db::prepareDateForDb(new DateTime()),
        ], ['id' => $recovery->id, 'status' => Recovery::STATUS_ACTIVE]);

        $label = Recovery::endReasonLabels()[$reason] ?? $reason;
        AbandonedCart::$plugin->getAttempts()->cancelScheduled($recovery, mb_strtolower($label));
        AbandonedCart::$plugin->getActivities()->log($recovery->id, Activities::TYPE_ENDED, null, ['reason' => $label]);

        if ($this->hasEventHandlers(self::EVENT_AFTER_END)) {
            $this->trigger(self::EVENT_AFTER_END, new RecoveryEvent(['recovery' => $this->getRecoveryById($recovery->id)]));
        }
    }

    public function endRecoveriesForCustomer(Customer $customer, string $reason): void
    {
        foreach ($this->_getActiveRecoveriesForCustomer($customer->id) as $recovery) {
            $this->endRecovery($recovery, $reason);
        }
    }

    /**
     * Ends recoveries whose time is up or whose cart is gone. Returns how many ended.
     */
    public function sweep(DateTime $now): int
    {
        $ended = 0;
        $rows = $this->_createRecoveryQuery()
            ->where(['status' => Recovery::STATUS_ACTIVE])
            ->andWhere(['or', ['<=', 'expiresAt', Db::prepareDateForDb($now)], ['orderId' => null]])
            ->limit(500)
            ->all();

        foreach ($rows as $row) {
            $recovery = new Recovery($row);

            if (!$recovery->orderId) {
                $this->endRecovery($recovery, Recovery::END_ORDER_REMOVED);
            } else {
                $unsent = (int)(new Query())->from([Table::ATTEMPTS])->where(['recoveryId' => $recovery->id, 'status' => [Attempt::STATUS_SCHEDULED, Attempt::STATUS_SENDING]])->count();
                $this->endRecovery($recovery, $unsent ? Recovery::END_EXPIRED : Recovery::END_FINISHED);
            }

            $ended++;
        }

        return $ended;
    }

    /**
     * When a cart in recovery changes, its next emails move later by the same amount: the customer is still shopping.
     */
    public function rescheduleChangedCarts(): void
    {
        $rows = (new Query())
            ->select(['r.id', 'r.abandonedAt', 'elements.dateUpdated'])
            ->from(['r' => Table::RECOVERIES])
            ->innerJoin(['elements' => '{{%elements}}'], '[[elements.id]] = [[r.orderId]]')
            ->innerJoin(['o' => '{{%commerce_orders}}'], '[[o.id]] = [[r.orderId]]')
            ->where(['r.status' => Recovery::STATUS_ACTIVE, 'o.isCompleted' => false])
            ->andWhere('[[elements.dateUpdated]] > [[r.abandonedAt]]')
            ->limit(500)
            ->all();

        foreach ($rows as $row) {
            $from = DateTimeHelper::toDateTime($row['abandonedAt']);
            $to = DateTimeHelper::toDateTime($row['dateUpdated']);
            $seconds = $to->getTimestamp() - $from->getTimestamp();

            Db::update(Table::RECOVERIES, ['abandonedAt' => Db::prepareDateForDb($to)], ['id' => $row['id']]);

            foreach ((new Query())->select(['id', 'dueAt'])->from([Table::ATTEMPTS])->where(['recoveryId' => $row['id'], 'status' => Attempt::STATUS_SCHEDULED])->all() as $attempt) {
                $due = DateTimeHelper::toDateTime($attempt['dueAt'])->modify("+{$seconds} seconds");
                Db::update(Table::ATTEMPTS, ['dueAt' => Db::prepareDateForDb($due)], ['id' => $attempt['id']]);
            }

            AbandonedCart::$plugin->getActivities()->log((int)$row['id'], Activities::TYPE_RESCHEDULED);
        }
    }

    public function getUrls(Recovery $recovery, ?Attempt $attempt = null): array
    {
        $security = Craft::$app->getSecurity();
        $customer = $recovery->getCustomer();
        // Padding is dropped so the tokens only use URL path characters; decoding doesn't need it.
        $recoverToken = rtrim(StringHelper::base64UrlEncode($security->hashData($recovery->uid . ':' . ($attempt?->uid ?? ''))), '=');
        $unsubscribeToken = rtrim(StringHelper::base64UrlEncode($security->hashData((string)$customer?->uid)), '=');
        $recoverUrl = UrlHelper::siteUrl('abandoned-cart/recover/' . $recoverToken, null, null, $recovery->siteId);
        $unsubscribeUrl = UrlHelper::siteUrl('abandoned-cart/unsubscribe/' . $unsubscribeToken, null, null, $recovery->siteId);

        if ($this->hasEventHandlers(self::EVENT_DEFINE_URLS)) {
            $event = new DefineUrlsEvent(['recovery' => $recovery, 'attempt' => $attempt, 'recoverUrl' => $recoverUrl, 'unsubscribeUrl' => $unsubscribeUrl]);
            $this->trigger(self::EVENT_DEFINE_URLS, $event);
            $recoverUrl = $event->recoverUrl;
            $unsubscribeUrl = $event->unsubscribeUrl;
        }

        return ['recoverUrl' => $recoverUrl, 'unsubscribeUrl' => $unsubscribeUrl];
    }

    /**
     * Returns [recovery, attempt] for a recovery link, or null when the link is invalid.
     */
    public function resolveRecoverToken(string $token): ?array
    {
        $data = Craft::$app->getSecurity()->validateData(StringHelper::base64UrlDecode($token));

        if (!is_string($data) || !str_contains($data, ':')) {
            return null;
        }

        [$recoveryUid, $attemptUid] = explode(':', $data, 2);
        $recovery = $this->getRecoveryByUid($recoveryUid);

        if (!$recovery) {
            return null;
        }

        $attempt = null;

        if ($attemptUid) {
            $attemptId = (new Query())->select(['id'])->from([Table::ATTEMPTS])->where(['uid' => $attemptUid, 'recoveryId' => $recovery->id])->scalar();
            $attempt = $attemptId ? AbandonedCart::$plugin->getAttempts()->getAttemptById((int)$attemptId) : null;
        }

        return [$recovery, $attempt];
    }

    public function resolveUnsubscribeToken(string $token): ?Customer
    {
        $uid = Craft::$app->getSecurity()->validateData(StringHelper::base64UrlDecode($token));

        if (!is_string($uid) || !$uid) {
            return null;
        }

        $id = (new Query())->select(['id'])->from([Table::CUSTOMERS])->where(['uid' => $uid])->scalar();

        return $id ? AbandonedCart::$plugin->getCustomers()->getCustomerById((int)$id) : null;
    }

    public function recordVisit(Recovery $recovery, ?Attempt $attempt, string $result): void
    {
        Db::insert(Table::VISITS, [
            'recoveryId' => $recovery->id,
            'attemptId' => $attempt?->id,
            'result' => $result,
        ]);

        if ($result === 'restored') {
            AbandonedCart::$plugin->getActivities()->log($recovery->id, Activities::TYPE_CLICKED, $attempt?->id, ['position' => $attempt?->position]);
        }
    }

    public function purgeHistory(): void
    {
        $days = max(AbandonedCart::$plugin->getSettings()->historyRetentionDays, 30);
        $cutoff = Db::prepareDateForDb((new DateTime())->sub(new DateInterval('P' . $days . 'D')));

        Db::delete(Table::RECOVERIES, ['and', ['not', ['status' => Recovery::STATUS_ACTIVE]], ['<', 'abandonedAt', $cutoff]]);
        Db::delete(Table::SKIPPED_CARTS, ['<', 'abandonedAt', $cutoff]);
        AbandonedCart::$plugin->getRuns()->purge();
    }


    // Private Methods
    // =========================================================================

    private function _readConsent(mixed $element, string $handle): ?bool
    {
        if (!$element || !method_exists($element, 'getFieldLayout') || !$element->getFieldLayout()?->getFieldByHandle($handle)) {
            return null;
        }

        return (bool)$element->getFieldValue($handle);
    }

    private function _wasContactedRecently(Customer $customer, Sequence $sequence, DateTime $now, Order $order): bool
    {
        $minutes = $sequence->getContactLimitMinutes();

        if ($minutes <= 0) {
            return false;
        }

        $since = (clone $now)->sub(new DateInterval('PT' . $minutes . 'M'));

        // Only an email that actually went out counts as contact.
        return (new Query())
            ->from(['a' => Table::ATTEMPTS])
            ->innerJoin(['r' => Table::RECOVERIES], '[[r.id]] = [[a.recoveryId]]')
            ->where(['r.customerId' => $customer->id, 'a.status' => Attempt::STATUS_SENT])
            ->andWhere(['not', ['r.orderId' => $order->id]])
            ->andWhere(['>=', 'a.sentAt', Db::prepareDateForDb($since)])
            ->exists();
    }

    private function _getActiveRecoveriesForCustomer(int $customerId): array
    {
        $rows = $this->_createRecoveryQuery()->where(['customerId' => $customerId, 'status' => Recovery::STATUS_ACTIVE])->all();

        return array_map(static fn(array $row): Recovery => new Recovery($row), $rows);
    }

    private function _createRecoveryQuery(): Query
    {
        return (new Query())
            ->select([
                'id',
                'orderId',
                'customerId',
                'siteId',
                'sequenceId',
                'sequenceVersionId',
                'status',
                'endReason',
                'cartTotal',
                'currency',
                'attributionWindowMinutes',
                'abandonedAt',
                'expiresAt',
                'endedAt',
                'recoveredAt',
                'creditedAttemptId',
                'clickedBeforeOrder',
                'orderTotal',
                'isFirstOrder',
                'couponCode',
                'dateCreated',
                'dateUpdated',
                'uid',
            ])
            ->from([Table::RECOVERIES]);
    }
}
