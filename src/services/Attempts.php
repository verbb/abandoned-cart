<?php
namespace verbb\abandonedcart\services;

use verbb\abandonedcart\AbandonedCart;
use verbb\abandonedcart\db\Table;
use verbb\abandonedcart\events\BeforeMailSend;
use verbb\abandonedcart\events\SendEvent;
use verbb\abandonedcart\models\Attempt;
use verbb\abandonedcart\models\CartSummary;
use verbb\abandonedcart\models\Customer;
use verbb\abandonedcart\models\Email;
use verbb\abandonedcart\models\Recovery;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;
use craft\helpers\StringHelper;

use DateInterval;
use DateTime;
use Throwable;
use yii\base\Event;

use craft\commerce\elements\Order;

class Attempts extends Component
{
    // Constants
    // =========================================================================

    public const EVENT_BEFORE_SEND = 'beforeSend';
    public const EVENT_AFTER_SEND = 'afterSend';

    // How long a send may hold an attempt before another run treats it as interrupted.
    public const LEASE_SECONDS = 300;
    public const RETRY_MINUTES = 15;


    // Public Methods
    // =========================================================================

    public function getAttemptsByRecoveryId(int $recoveryId): array
    {
        $rows = $this->_createAttemptQuery()->where(['recoveryId' => $recoveryId])->orderBy(['position' => SORT_ASC])->all();

        return array_map(static fn(array $row): Attempt => new Attempt($row), $rows);
    }

    public function getAttemptById(int $id): ?Attempt
    {
        $row = $this->_createAttemptQuery()->where(['id' => $id])->one();

        return $row ? new Attempt($row) : null;
    }

    public function getNextScheduledAttempt(Recovery $recovery): ?Attempt
    {
        $row = $this->_createAttemptQuery()
            ->where(['recoveryId' => $recovery->id, 'status' => Attempt::STATUS_SCHEDULED])
            ->orderBy(['position' => SORT_ASC])
            ->one();

        return $row ? new Attempt($row) : null;
    }

    /**
     * Schedules one attempt per email in the sequence version's snapshot, counted from when the cart was abandoned.
     */
    public function scheduleAttempts(Recovery $recovery, array $snapshotEmails, int $abandonedAfterMinutes): void
    {
        $due = DateTimeHelper::toDateTime($recovery->abandonedAt)->add(new DateInterval('PT' . max(0, $abandonedAfterMinutes) . 'M'));

        foreach ($snapshotEmails as $email) {
            $due = (clone $due)->add(new DateInterval('PT' . max(0, (int)$email['delayMinutes']) . 'M'));

            Db::insert(Table::ATTEMPTS, [
                'recoveryId' => $recovery->id,
                'emailId' => $email['id'] ?? null,
                'position' => (int)$email['position'],
                'status' => Attempt::STATUS_SCHEDULED,
                'dueAt' => Db::prepareDateForDb($due),
            ]);
        }
    }

    /**
     * Sends what's due, oldest first. Emails for sequences that are switched off, and everything while sending is
     * paused, wait in place and go out once they're back on.
     */
    public function sendDue(DateTime $now, int $limit = 100): array
    {
        $summary = ['sent' => 0, 'failed' => 0, 'cancelled' => 0];

        $this->releaseStaleLeases($now);

        if (AbandonedCart::$plugin->getSettings()->getDeliveryPaused()) {
            return $summary;
        }

        $ids = (new Query())
            ->select(['a.id'])
            ->from(['a' => Table::ATTEMPTS])
            ->innerJoin(['r' => Table::RECOVERIES], '[[r.id]] = [[a.recoveryId]]')
            ->leftJoin(['s' => Table::SEQUENCES], '[[s.id]] = [[r.sequenceId]]')
            ->where(['a.status' => Attempt::STATUS_SCHEDULED, 'r.status' => Recovery::STATUS_ACTIVE])
            ->andWhere(['<=', 'a.dueAt', Db::prepareDateForDb($now)])
            ->andWhere(['or', ['s.enabled' => true], ['s.id' => null]])
            ->orderBy(['a.dueAt' => SORT_ASC, 'a.id' => SORT_ASC])
            ->limit($limit)
            ->column();

        foreach ($ids as $id) {
            $attempt = $this->claim((int)$id, $now);

            if (!$attempt) {
                continue;
            }

            $result = $this->deliver($attempt, $now);
            $summary[$result]++;
        }

        return $summary;
    }

    public function claim(int $attemptId, DateTime $now): ?Attempt
    {
        $token = StringHelper::randomString(32);

        // Only one run can move an attempt from scheduled to sending, so an email is never sent twice.
        $claimed = Db::update(Table::ATTEMPTS, [
            'status' => Attempt::STATUS_SENDING,
            'leaseToken' => $token,
            'leaseExpiresAt' => Db::prepareDateForDb((clone $now)->add(new DateInterval('PT' . self::LEASE_SECONDS . 'S'))),
        ], ['id' => $attemptId, 'status' => Attempt::STATUS_SCHEDULED]);

        return $claimed ? $this->getAttemptById($attemptId) : null;
    }

    /**
     * Returns 'sent', 'failed' or 'cancelled'.
     */
    public function deliver(Attempt $attempt, DateTime $now): string
    {
        $recoveries = AbandonedCart::$plugin->getRecoveries();
        $recovery = $recoveries->getRecoveryById((int)$attempt->recoveryId);

        if (!$recovery || !$recovery->getIsActive()) {
            return $this->_cancel($attempt, Craft::t('abandoned-cart', 'the cart had already left recovery'));
        }

        if ($recovery->expiresAt && $recovery->expiresAt <= $now) {
            $this->_cancel($attempt, Craft::t('abandoned-cart', 'the recovery period ended'));
            $recoveries->endRecovery($recovery, Recovery::END_EXPIRED);

            return 'cancelled';
        }

        $order = $recovery->getOrder();

        if (!$order) {
            $this->_cancel($attempt, Craft::t('abandoned-cart', 'the cart was deleted'));
            $recoveries->endRecovery($recovery, Recovery::END_ORDER_REMOVED);

            return 'cancelled';
        }

        if ($order->isCompleted) {
            $this->_release($attempt);
            $recoveries->handleCompletedOrder($order);

            return 'cancelled';
        }

        if (!$order->getLineItems()) {
            $this->_cancel($attempt, Craft::t('abandoned-cart', 'the cart is empty'));
            $recoveries->endRecovery($recovery, Recovery::END_CART_EMPTIED);

            return 'cancelled';
        }

        $customer = $recovery->getCustomer();

        if (!$customer || !$customer->getIsEmailable()) {
            $this->_cancel($attempt, Craft::t('abandoned-cart', 'the customer can’t be emailed'));
            $recoveries->endRecovery($recovery, $customer?->status === Customer::STATUS_UNSUBSCRIBED ? Recovery::END_UNSUBSCRIBED : Recovery::END_DO_NOT_EMAIL);

            return 'cancelled';
        }

        $email = $attempt->getEmail();

        if (!$email) {
            return $this->_cancel($attempt, Craft::t('abandoned-cart', 'the email was deleted from its sequence'));
        }

        return $this->_send($attempt, $recovery, $order, $email, $now);
    }

    /**
     * Sends the next scheduled email straight away, for Send Now on a cart. Works while a sequence is off, but not
     * while all sending is paused.
     */
    public function sendNow(Recovery $recovery): ?string
    {
        $next = $this->getNextScheduledAttempt($recovery);

        if (!$next || AbandonedCart::$plugin->getSettings()->getDeliveryPaused()) {
            return null;
        }

        $now = new DateTime();
        $attempt = $this->claim($next->id, $now);

        if (!$attempt) {
            return null;
        }

        AbandonedCart::$plugin->getActivities()->log($recovery->id, Activities::TYPE_SEND_NOW, $attempt->id);
        $result = $this->deliver($attempt, $now);

        // Later emails keep their gaps from this one rather than arriving in a burst.
        if ($result === 'sent') {
            $this->_shiftLaterAttempts($recovery, $attempt, $next->dueAt, $now);
        }

        return $result;
    }

    /**
     * Sends a failed email again, straight away. Returns null when the email didn't fail or the cart has left recovery.
     */
    public function retry(Recovery $recovery, Attempt $attempt): ?string
    {
        if ($attempt->status !== Attempt::STATUS_FAILED || !$recovery->getIsActive() || AbandonedCart::$plugin->getSettings()->getDeliveryPaused()) {
            return null;
        }

        $now = new DateTime();

        // Staff asked for this send, so it gets a fresh set of tries.
        Db::update(Table::ATTEMPTS, ['status' => Attempt::STATUS_SCHEDULED, 'tries' => 0, 'dueAt' => Db::prepareDateForDb($now)], ['id' => $attempt->id, 'status' => Attempt::STATUS_FAILED]);
        $claimed = $this->claim($attempt->id, $now);

        if (!$claimed) {
            return null;
        }

        AbandonedCart::$plugin->getActivities()->log($recovery->id, Activities::TYPE_RETRIED, $claimed->id, ['position' => $claimed->position]);

        return $this->deliver($claimed, $now);
    }

    public function sendTest(Email $email, string $to, ?Order $order = null): bool
    {
        $cart = $order ? CartSummary::fromOrder($order) : CartSummary::sample($email->getSequence()?->siteIds[0] ?? null);
        $rendered = AbandonedCart::$plugin->getEmailRenderer()->render($email, $cart, $order, ['recoverUrl' => '#', 'unsubscribeUrl' => '#'], true);

        $message = Craft::$app->getMailer()->compose()
            ->setTo($to)
            ->setSubject(Craft::t('abandoned-cart', '[Test] {subject}', ['subject' => $rendered->subject]))
            ->setHtmlBody($rendered->html)
            ->setTextBody($rendered->text);

        $sent = $message->send();

        if ($sent) {
            AbandonedCart::$plugin->getState()->set(State::TEST_SENT_AT, (new DateTime())->format(DATE_ATOM));
        }

        return $sent;
    }

    public function cancelScheduled(Recovery $recovery, string $reason): void
    {
        Db::update(Table::ATTEMPTS, [
            'status' => Attempt::STATUS_CANCELLED,
            'failureReason' => $reason,
            'leaseToken' => null,
            'leaseExpiresAt' => null,
        ], ['recoveryId' => $recovery->id, 'status' => Attempt::STATUS_SCHEDULED]);
    }

    public function releaseStaleLeases(DateTime $now): void
    {
        // A worker that died mid-send leaves the attempt held. Nobody knows whether the mailer sent it, so it isn't retried.
        Db::update(Table::ATTEMPTS, [
            'status' => Attempt::STATUS_FAILED,
            'failureReason' => Craft::t('abandoned-cart', 'Sending was interrupted, so it isn’t known whether the email went out.'),
            'leaseToken' => null,
            'leaseExpiresAt' => null,
        ], ['and', ['status' => Attempt::STATUS_SENDING], ['<', 'leaseExpiresAt', Db::prepareDateForDb($now)]]);
    }


    // Private Methods
    // =========================================================================

    private function _send(Attempt $attempt, Recovery $recovery, Order $order, Email $email, DateTime $now): string
    {
        $recoveries = AbandonedCart::$plugin->getRecoveries();

        try {
            $cart = CartSummary::fromOrder($order);
            $urls = $recoveries->getUrls($recovery, $attempt);
            $rendered = AbandonedCart::$plugin->getEmailRenderer()->render($email, $cart, $order, $urls);

            $message = Craft::$app->getMailer()->compose()
                ->setTo($order->getEmail())
                ->setSubject($rendered->subject)
                ->setHtmlBody($rendered->html)
                ->setTextBody($rendered->text);

            AbandonedCart::$plugin->getEmailRenderer()->applyHeaders($message, $rendered);

            if ($this->hasEventHandlers(self::EVENT_BEFORE_SEND)) {
                $event = new SendEvent(['attempt' => $attempt, 'recovery' => $recovery, 'email' => $email, 'rendered' => $rendered, 'message' => $message]);
                $this->trigger(self::EVENT_BEFORE_SEND, $event);

                if (!$event->isValid) {
                    return $this->_cancel($attempt, Craft::t('abandoned-cart', 'a plugin or module cancelled it'));
                }

                $message = $event->message ?? $message;
            }

            // 4.x handlers keep working while compatibility mode is on.
            if (AbandonedCart::$plugin->getSettings()->compatibilityMode && Event::hasHandlers(Carts::class, Carts::EVENT_BEFORE_MAIL_SEND)) {
                Craft::$app->getDeprecator()->log('Carts::EVENT_BEFORE_MAIL_SEND', 'Abandoned Cart’s `Carts::EVENT_BEFORE_MAIL_SEND` event is deprecated. Listen to `Attempts::EVENT_BEFORE_SEND` instead.');
                $legacyEvent = new BeforeMailSend(['order' => $order, 'message' => $message]);
                Event::trigger(Carts::class, Carts::EVENT_BEFORE_MAIL_SEND, $legacyEvent);

                if (!$legacyEvent->isValid) {
                    return $this->_cancel($attempt, Craft::t('abandoned-cart', 'a plugin or module cancelled it'));
                }

                $message = $legacyEvent->message;
            }

            $sent = $message->send();
        } catch (Throwable $e) {
            Craft::error('Abandoned Cart couldn’t send email ' . $attempt->position . ' for recovery ' . $recovery->id . ': ' . $e->getMessage(), __METHOD__);
            $sent = false;
            $rendered = $rendered ?? null;
            $failure = $e->getMessage();
        }

        if ($sent) {
            Db::update(Table::ATTEMPTS, [
                'status' => Attempt::STATUS_SENT,
                'sentAt' => Db::prepareDateForDb($now),
                'subject' => mb_substr((string)$rendered?->subject, 0, 255),
                'tries' => $attempt->tries + 1,
                'leaseToken' => null,
                'leaseExpiresAt' => null,
                'failureReason' => null,
            ], ['id' => $attempt->id]);

            AbandonedCart::$plugin->getActivities()->log($recovery->id, Activities::TYPE_SENT, $attempt->id, ['position' => $attempt->position, 'subject' => $rendered?->subject]);

            // Kept apart from the attempt, so the Overview stays past setup once a customer and their history are deleted.
            $state = AbandonedCart::$plugin->getState();

            if (!$state->get(State::FIRST_SENT_AT)) {
                $state->set(State::FIRST_SENT_AT, $now->format(DATE_ATOM));
            }
        } else {
            $tries = $attempt->tries + 1;
            $reason = $failure ?? Craft::t('abandoned-cart', 'The mailer reported that it couldn’t send the email.');
            $retry = $tries < Attempt::MAX_TRIES;

            Db::update(Table::ATTEMPTS, [
                'status' => $retry ? Attempt::STATUS_SCHEDULED : Attempt::STATUS_FAILED,
                'dueAt' => $retry ? Db::prepareDateForDb((clone $now)->add(new DateInterval('PT' . (self::RETRY_MINUTES * $tries) . 'M'))) : Db::prepareDateForDb($attempt->dueAt),
                'tries' => $tries,
                'failureReason' => mb_substr($reason, 0, 255),
                'leaseToken' => null,
                'leaseExpiresAt' => null,
            ], ['id' => $attempt->id]);

            AbandonedCart::$plugin->getActivities()->log($recovery->id, Activities::TYPE_FAILED, $attempt->id, ['position' => $attempt->position, 'reason' => $reason, 'retrying' => $retry]);
        }

        if ($this->hasEventHandlers(self::EVENT_AFTER_SEND)) {
            $this->trigger(self::EVENT_AFTER_SEND, new SendEvent(['attempt' => $this->getAttemptById($attempt->id), 'recovery' => $recovery, 'email' => $email, 'rendered' => $rendered ?? null, 'message' => $message ?? null, 'success' => (bool)$sent]));
        }

        return $sent ? 'sent' : 'failed';
    }

    private function _cancel(Attempt $attempt, string $reason): string
    {
        Db::update(Table::ATTEMPTS, [
            'status' => Attempt::STATUS_CANCELLED,
            'failureReason' => mb_substr($reason, 0, 255),
            'leaseToken' => null,
            'leaseExpiresAt' => null,
        ], ['id' => $attempt->id]);

        AbandonedCart::$plugin->getActivities()->log((int)$attempt->recoveryId, Activities::TYPE_CANCELLED, $attempt->id, ['position' => $attempt->position, 'reason' => $reason]);

        return 'cancelled';
    }

    private function _release(Attempt $attempt): void
    {
        Db::update(Table::ATTEMPTS, ['status' => Attempt::STATUS_SCHEDULED, 'leaseToken' => null, 'leaseExpiresAt' => null], ['id' => $attempt->id]);
    }

    private function _shiftLaterAttempts(Recovery $recovery, Attempt $sent, ?DateTime $originalDue, DateTime $now): void
    {
        if (!$originalDue || $originalDue <= $now) {
            return;
        }

        $seconds = $originalDue->getTimestamp() - $now->getTimestamp();

        foreach ($this->getAttemptsByRecoveryId($recovery->id) as $attempt) {
            if ($attempt->status === Attempt::STATUS_SCHEDULED && $attempt->position > $sent->position && $attempt->dueAt) {
                Db::update(Table::ATTEMPTS, ['dueAt' => Db::prepareDateForDb((clone $attempt->dueAt)->modify("-{$seconds} seconds"))], ['id' => $attempt->id]);
            }
        }
    }

    private function _createAttemptQuery(): Query
    {
        return (new Query())
            ->select([
                'id',
                'recoveryId',
                'emailId',
                'position',
                'status',
                'dueAt',
                'sentAt',
                'subject',
                'tries',
                'leaseToken',
                'leaseExpiresAt',
                'failureReason',
                'dateCreated',
                'dateUpdated',
                'uid',
            ])
            ->from([Table::ATTEMPTS]);
    }
}
