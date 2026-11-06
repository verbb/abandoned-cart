<?php
namespace verbb\abandonedcart\controllers;

use verbb\abandonedcart\AbandonedCart;
use verbb\abandonedcart\models\Attempt;
use verbb\abandonedcart\models\CartSummary;
use verbb\abandonedcart\models\Customer;
use verbb\abandonedcart\models\Recovery;
use verbb\abandonedcart\models\SkippedCart;

use Craft;
use craft\helpers\AdminTable;
use craft\helpers\DateTimeHelper;
use craft\helpers\UrlHelper;

use yii\web\NotFoundHttpException;
use yii\web\Response;

use craft\commerce\elements\Order;

class CartsController extends BaseCpController
{
    // Public Methods
    // =========================================================================

    public function actionIndex(): Response
    {
        return $this->renderTemplate('abandoned-cart/carts/index', [
            'counts' => AbandonedCart::$plugin->getReports()->getCartCounts(),
            'sequences' => AbandonedCart::$plugin->getSequences()->getAllSequences(),
            'reasons' => SkippedCart::reasonLabels(),
        ]);
    }

    public function actionList(): Response
    {
        $this->requireAcceptsJson();

        $plugin = AbandonedCart::$plugin;
        $page = max(1, (int)$this->request->getParam('page', 1));
        $pageSize = min(100, max(10, (int)$this->request->getParam('per_page', 50)));
        $sort = $this->request->getParam('sort')[0] ?? [];
        $result = $plugin->getReports()->getCartList([
            'status' => $this->request->getParam('status', 'all'),
            'search' => $this->request->getParam('search'),
            'sequenceId' => $this->request->getParam('sequenceId'),
            'reason' => $this->request->getParam('reason'),
        ], $page, $pageSize, (string)($sort['sortField'] ?? 'abandonedAt'), (string)($sort['direction'] ?? 'desc'));

        $recoveryIds = array_map(static fn(array $row): int => (int)$row['id'], array_filter($result['rows'], static fn(array $row): bool => $row['kind'] === 'recovery'));
        $progress = $plugin->getReports()->getEmailProgress($recoveryIds);
        $sequences = [];

        foreach ($plugin->getSequences()->getAllSequences() as $sequence) {
            $sequences[$sequence->id] = $sequence->name;
        }

        $formatter = Craft::$app->getFormatter();
        $users = $this->_userNames(array_filter(array_column($result['rows'], 'customerUserId')));

        $rows = array_map(function(array $row) use ($progress, $sequences, $formatter, $users): array {
            $isRecovery = $row['kind'] === 'recovery';
            $state = $row['state'];

            return [
                'id' => $row['kind'] . '-' . $row['id'],
                'url' => UrlHelper::cpUrl($isRecovery ? "abandoned-cart/carts/{$row['id']}" : "abandoned-cart/carts/skipped/{$row['id']}"),
                'customer' => $users[$row['customerUserId']] ?? null,
                'email' => $row['email'],
                'cartTotal' => (float)$row['cartTotal'],
                'cartTotalLabel' => $formatter->asCurrency($row['cartTotal'], $row['currency'] ?: AbandonedCart::$plugin->getReports()->getCurrency()),
                'sequence' => $row['sequenceId'] ? ($sequences[$row['sequenceId']] ?? Craft::t('abandoned-cart', 'Deleted sequence')) : null,
                'progress' => $isRecovery ? array_map(static fn(array $step): string => $step['status'], $progress[(int)$row['id']] ?? []) : [],
                'state' => $state,
                'stateLabel' => self::stateLabels()[$state],
                'detail' => $isRecovery
                    ? ($row['reason'] ? (Recovery::endReasonLabels()[$row['reason']] ?? $row['reason']) : ($state === 'recovery' ? $this->_nextLabel($progress[(int)$row['id']] ?? []) : null))
                    : (SkippedCart::reasonLabels()[$row['reason']] ?? $row['reason']),
                'abandonedAt' => $formatter->asDatetime(DateTimeHelper::toDateTime($row['abandonedAt']), 'short'),
            ];
        }, $result['rows']);

        return $this->asJson([
            'pagination' => AdminTable::paginationLinks($page, $result['total'], $pageSize),
            'data' => $rows,
        ]);
    }

    public function actionView(int $recoveryId): Response
    {
        $plugin = AbandonedCart::$plugin;
        $recovery = $plugin->getRecoveries()->getRecoveryById($recoveryId);

        if (!$recovery) {
            throw new NotFoundHttpException('Cart not found');
        }

        $order = $recovery->getOrder();
        $attempts = $recovery->getAttempts();
        $activities = $plugin->getActivities()->getActivitiesByRecoveryId($recovery->id);
        $customer = $recovery->getCustomer();
        $sequence = $recovery->getSequence();

        return $this->renderTemplate('abandoned-cart/carts/_view', [
            'recovery' => $recovery,
            'order' => $order,
            'cart' => $order ? CartSummary::fromOrder($order) : null,
            'attempts' => $attempts,
            'nextAttempt' => $plugin->getAttempts()->getNextScheduledAttempt($recovery),
            'creditedAttempt' => $recovery->creditedAttemptId ? $plugin->getAttempts()->getAttemptById($recovery->creditedAttemptId) : null,
            'activities' => array_map(static fn(array $activity): array => $activity + ['label' => $plugin->getActivities()->describe($activity)], $activities),
            'customer' => $customer,
            'customerName' => $customer?->getUser()?->getFullName(),
            'previousOrders' => $order ? $plugin->getAudiences()->countPreviousOrders($order) : null,
            'sequence' => $sequence,
            'audience' => $sequence ? $plugin->getAudiences()->describe($sequence) : null,
            'clicked' => array_unique(array_column(array_filter($activities, static fn(array $a): bool => $a['type'] === 'clicked'), 'attemptId')),
        ]);
    }

    public function actionViewSkipped(int $skippedCartId): Response
    {
        $plugin = AbandonedCart::$plugin;
        $skipped = $plugin->getRecoveries()->getSkippedCartById($skippedCartId);

        if (!$skipped) {
            throw new NotFoundHttpException('Cart not found');
        }

        $order = $skipped->orderId ? Order::find()->id($skipped->orderId)->status(null)->one() : null;
        $customer = $skipped->customerId ? $plugin->getCustomers()->getCustomerById($skipped->customerId) : null;

        return $this->renderTemplate('abandoned-cart/carts/_skipped', [
            'skipped' => $skipped,
            'order' => $order,
            'cart' => $order ? CartSummary::fromOrder($order) : null,
            'customer' => $customer,
            'customerName' => $customer?->getUser()?->getFullName(),
            'consentField' => $plugin->getSettings()->consentFieldHandle,
        ]);
    }

    public function actionSendNow(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission(AbandonedCart::PERMISSION_MANAGE_SEQUENCES);

        $recovery = $this->_getRecovery();
        $result = AbandonedCart::$plugin->getAttempts()->sendNow($recovery);

        return match ($result) {
            'sent' => $this->asSuccess(Craft::t('abandoned-cart', 'The next email was sent.')),
            null => $this->asFailure(Craft::t('abandoned-cart', 'There’s no email waiting to send, or sending is paused.')),
            default => $this->asFailure(Craft::t('abandoned-cart', 'The email wasn’t sent. The cart’s history shows why.')),
        };
    }

    public function actionRetry(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission(AbandonedCart::PERMISSION_MANAGE_SEQUENCES);

        $recovery = $this->_getRecovery();
        $attempts = AbandonedCart::$plugin->getAttempts();
        $attempt = $attempts->getAttemptById((int)$this->request->getRequiredBodyParam('attemptId'));

        if (!$attempt || $attempt->recoveryId !== $recovery->id) {
            throw new NotFoundHttpException('Email not found');
        }

        return match ($attempts->retry($recovery, $attempt)) {
            'sent' => $this->asSuccess(Craft::t('abandoned-cart', 'Email {position} was sent.', ['position' => $attempt->position])),
            null => $this->asFailure(Craft::t('abandoned-cart', 'That email can’t be retried, or sending is paused.')),
            default => $this->asFailure(Craft::t('abandoned-cart', 'The email wasn’t sent. The cart’s history shows why.')),
        };
    }

    public function actionStop(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission(AbandonedCart::PERMISSION_MANAGE_SEQUENCES);

        AbandonedCart::$plugin->getRecoveries()->endRecovery($this->_getRecovery(), Recovery::END_STOPPED);

        return $this->asSuccess(Craft::t('abandoned-cart', 'Emails stopped for this cart.'));
    }

    public static function stateLabels(): array
    {
        return [
            'recovery' => Craft::t('abandoned-cart', 'In recovery'),
            'recovered' => Craft::t('abandoned-cart', 'Recovered'),
            'not' => Craft::t('abandoned-cart', 'Not recovered'),
            'skipped' => Craft::t('abandoned-cart', 'Skipped'),
        ];
    }


    // Private Methods
    // =========================================================================

    private function _getRecovery(): Recovery
    {
        $recovery = AbandonedCart::$plugin->getRecoveries()->getRecoveryById((int)$this->request->getRequiredBodyParam('recoveryId'));

        if (!$recovery) {
            throw new NotFoundHttpException('Cart not found');
        }

        return $recovery;
    }

    private function _nextLabel(array $steps): ?string
    {
        foreach ($steps as $step) {
            if ($step['status'] === Attempt::STATUS_SCHEDULED) {
                $dueAt = DateTimeHelper::toDateTime($step['dueAt']);

                if (!$dueAt || $dueAt <= DateTimeHelper::now()) {
                    return Craft::t('abandoned-cart', 'Email {position} on the next run', ['position' => $step['position']]);
                }

                return Craft::t('abandoned-cart', 'Email {position} {time}', [
                    'position' => $step['position'],
                    'time' => Craft::$app->getFormatter()->asRelativeTime($dueAt),
                ]);
            }
        }

        return $steps ? Craft::t('abandoned-cart', 'All emails sent') : null;
    }

    private function _userNames(array $userIds): array
    {
        if (!$userIds) {
            return [];
        }

        return (new \craft\db\Query())->select(['id', 'fullName'])->from(['{{%users}}'])->where(['id' => array_values(array_unique($userIds))])->pairs();
    }
}
