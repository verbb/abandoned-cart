<?php
namespace verbb\abandonedcart\services;

use verbb\abandonedcart\db\Table;
use verbb\abandonedcart\models\Attempt;
use verbb\abandonedcart\models\Email;
use verbb\abandonedcart\models\Recovery;
use verbb\abandonedcart\models\SkippedCart;

use craft\base\Component;
use craft\db\Query;
use craft\helpers\Db;

use yii\db\Expression;

use DateTime;

use craft\commerce\Plugin as Commerce;

/**
 * Read-only queries behind the Overview, Carts, Customers and Sequences screens.
 */
class Reports extends Component
{
    // Public Methods
    // =========================================================================

    public function getCurrency(): string
    {
        return Commerce::getInstance()?->getStores()->getPrimaryStore()?->getCurrency()?->getCode() ?? 'USD';
    }

    public function hasAnyRecoveries(): bool
    {
        return (new Query())->from([Table::RECOVERIES])->exists();
    }

    public function countActiveRecoveries(?int $sequenceId = null): int
    {
        return (int)(new Query())
            ->from([Table::RECOVERIES])
            ->where(['status' => Recovery::STATUS_ACTIVE])
            ->andFilterWhere(['sequenceId' => $sequenceId])
            ->count();
    }

    /**
     * Carts entered, recovered and revenue per sequence since a date, keyed by sequence ID.
     */
    public function getSequenceStats(DateTime $since): array
    {
        $rows = (new Query())
            ->select([
                'sequenceId',
                'entered' => 'COUNT(*)',
                'recovered' => 'SUM(CASE WHEN [[status]] = :recovered THEN 1 ELSE 0 END)',
                'revenue' => 'SUM(CASE WHEN [[status]] = :recovered THEN [[orderTotal]] ELSE 0 END)',
            ])
            ->from([Table::RECOVERIES])
            ->where(['>=', 'abandonedAt', Db::prepareDateForDb($since)])
            ->andWhere(['not', ['sequenceId' => null]])
            ->groupBy(['sequenceId'])
            ->addParams([':recovered' => Recovery::STATUS_RECOVERED])
            ->all();

        $stats = [];

        foreach ($rows as $row) {
            $entered = (int)$row['entered'];
            $recovered = (int)$row['recovered'];
            $stats[(int)$row['sequenceId']] = [
                'entered' => $entered,
                'recovered' => $recovered,
                'revenue' => (float)$row['revenue'],
                'rate' => $entered ? $recovered / $entered : 0,
            ];
        }

        return $stats;
    }

    /**
     * Sent, clicked and credited recoveries per email since a date, keyed by email ID.
     *
     * @param Email[] $emails
     */
    public function getEmailStats(array $emails, DateTime $since): array
    {
        $ids = array_filter(array_map(static fn(Email $email): ?int => $email->id, $emails));

        if (!$ids) {
            return [];
        }

        $sent = (new Query())
            ->select(['emailId', 'count' => 'COUNT(*)'])
            ->from([Table::ATTEMPTS])
            ->where(['emailId' => $ids, 'status' => Attempt::STATUS_SENT])
            ->andWhere(['>=', 'sentAt', Db::prepareDateForDb($since)])
            ->groupBy(['emailId'])
            ->pairs();

        $clicked = (new Query())
            ->select(['a.emailId', 'count' => 'COUNT(DISTINCT [[v.attemptId]])'])
            ->from(['v' => Table::VISITS])
            ->innerJoin(['a' => Table::ATTEMPTS], '[[a.id]] = [[v.attemptId]]')
            ->where(['a.emailId' => $ids, 'v.result' => 'restored'])
            ->andWhere(['>=', 'a.sentAt', Db::prepareDateForDb($since)])
            ->groupBy(['a.emailId'])
            ->pairs();

        $credited = (new Query())
            ->select(['a.emailId', 'count' => 'COUNT(*)', 'revenue' => 'SUM([[r.orderTotal]])'])
            ->from(['r' => Table::RECOVERIES])
            ->innerJoin(['a' => Table::ATTEMPTS], '[[a.id]] = [[r.creditedAttemptId]]')
            ->where(['a.emailId' => $ids, 'r.status' => Recovery::STATUS_RECOVERED])
            ->andWhere(['>=', 'r.recoveredAt', Db::prepareDateForDb($since)])
            ->groupBy(['a.emailId'])
            ->all();

        $stats = [];

        foreach ($ids as $id) {
            $sentCount = (int)($sent[$id] ?? 0);
            $stats[$id] = [
                'sent' => $sentCount,
                'clicked' => (int)($clicked[$id] ?? 0),
                'clickRate' => $sentCount ? (int)($clicked[$id] ?? 0) / $sentCount : null,
                'recovered' => 0,
                'revenue' => 0.0,
            ];
        }

        foreach ($credited as $row) {
            $stats[(int)$row['emailId']]['recovered'] = (int)$row['count'];
            $stats[(int)$row['emailId']]['revenue'] = (float)$row['revenue'];
        }

        return $stats;
    }

    // Lists
    // =========================================================================

    /**
     * One page of the Carts list: carts in recovery, recovered, not recovered, and skipped, newest first by default.
     *
     * @return array{rows: array, total: int}
     */
    public function getCartList(array $filters, int $page = 1, int $pageSize = 50, string $sort = 'abandonedAt', string $direction = 'desc'): array
    {
        $status = $filters['status'] ?? 'all';
        $search = trim((string)($filters['search'] ?? ''));
        $parts = [];

        if ($status !== 'skipped') {
            $recoveries = (new Query())
                ->select([
                    'kind' => new Expression("'recovery'"),
                    'r.id', 'r.orderId', 'r.customerId', 'r.sequenceId',
                    'state' => new Expression("CASE WHEN [[r.status]] = 'active' THEN 'recovery' WHEN [[r.status]] = 'recovered' THEN 'recovered' ELSE 'not' END"),
                    'reason' => 'r.endReason',
                    'r.cartTotal', 'r.currency', 'r.abandonedAt',
                ])
                ->from(['r' => Table::RECOVERIES]);

            if (in_array($status, ['recovery', 'recovered', 'not'], true)) {
                $recoveries->andWhere(match ($status) {
                    'recovery' => ['r.status' => Recovery::STATUS_ACTIVE],
                    'recovered' => ['r.status' => Recovery::STATUS_RECOVERED],
                    default => ['r.status' => Recovery::STATUS_ENDED],
                });
            }

            $this->_applyListFilters($recoveries, 'r', $filters);
            $parts[] = $recoveries;
        }

        if (in_array($status, ['all', 'skipped'], true)) {
            $skipped = (new Query())
                ->select([
                    'kind' => new Expression("'skipped'"),
                    's.id', 's.orderId', 's.customerId',
                    'sequenceId' => new Expression('NULL'),
                    'state' => new Expression("'skipped'"),
                    'reason' => 's.reason',
                    's.cartTotal', 's.currency', 's.abandonedAt',
                ])
                ->from(['s' => Table::SKIPPED_CARTS]);

            if (!empty($filters['reason'])) {
                $skipped->andWhere(['s.reason' => $filters['reason']]);
            }

            if (!empty($filters['sequenceId'])) {
                $skipped->andWhere('1 = 0');
            }

            $this->_applyListFilters($skipped, 's', $filters);
            $parts[] = $skipped;
        }

        $union = array_shift($parts);

        foreach ($parts as $part) {
            $union->union($part, true);
        }

        $query = (new Query())->from(['u' => $union])->leftJoin(['c' => Table::CUSTOMERS], '[[c.id]] = [[u.customerId]]');

        if ($search !== '') {
            $query->andWhere(['like', 'c.email', $search]);
        }

        $total = (int)(clone $query)->count();
        $sortColumn = match ($sort) {
            'cartTotal' => 'u.cartTotal',
            'customer' => 'c.email',
            default => 'u.abandonedAt',
        };
        $rows = $query
            ->select(['u.*', 'email' => 'c.email', 'customerUserId' => 'c.userId'])
            ->orderBy([$sortColumn => $direction === 'asc' ? SORT_ASC : SORT_DESC, 'u.id' => SORT_DESC])
            ->offset(max(0, $page - 1) * $pageSize)
            ->limit($pageSize)
            ->all();

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * Statuses and clicks of each recovery's emails, for the progress bars in lists.
     */
    public function getEmailProgress(array $recoveryIds): array
    {
        if (!$recoveryIds) {
            return [];
        }

        $clicked = (new Query())->select(['attemptId'])->from([Table::VISITS])->where(['recoveryId' => $recoveryIds, 'result' => 'restored'])->column();
        $rows = (new Query())
            ->select(['id', 'recoveryId', 'position', 'status', 'dueAt'])
            ->from([Table::ATTEMPTS])
            ->where(['recoveryId' => $recoveryIds])
            ->orderBy(['position' => SORT_ASC])
            ->all();

        $progress = [];

        foreach ($rows as $row) {
            $progress[(int)$row['recoveryId']][] = [
                'position' => (int)$row['position'],
                'status' => in_array($row['id'], $clicked) ? 'clicked' : $row['status'],
                'dueAt' => $row['dueAt'],
            ];
        }

        return $progress;
    }

    public function getCartCounts(): array
    {
        $recoveries = (new Query())->select(['status', 'count' => 'COUNT(*)'])->from([Table::RECOVERIES])->groupBy(['status'])->pairs();
        $skipped = (int)(new Query())->from([Table::SKIPPED_CARTS])->count();
        $active = (int)($recoveries[Recovery::STATUS_ACTIVE] ?? 0);
        $recovered = (int)($recoveries[Recovery::STATUS_RECOVERED] ?? 0);
        $ended = (int)($recoveries[Recovery::STATUS_ENDED] ?? 0);

        $sequences = (new Query())->select(['sequenceId', 'count' => 'COUNT(*)'])->from([Table::RECOVERIES])->where(['not', ['sequenceId' => null]])->groupBy(['sequenceId'])->pairs();

        return [
            'all' => $active + $recovered + $ended + $skipped,
            'recovery' => $active,
            'recovered' => $recovered,
            'not' => $ended,
            'skipped' => $skipped,
            'sequences' => array_map('intval', $sequences),
        ];
    }

    /**
     * One page of the Customers list, with their carts and recoveries.
     *
     * @return array{rows: array, total: int}
     */
    public function getCustomerList(array $filters, int $page = 1, int $pageSize = 50, string $sort = 'carts', string $direction = 'desc'): array
    {
        $recoveryStats = (new Query())
            ->select([
                'customerId',
                'carts' => 'COUNT(*)',
                'recovered' => "SUM(CASE WHEN [[status]] = 'recovered' THEN 1 ELSE 0 END)",
                'revenue' => "SUM(CASE WHEN [[status]] = 'recovered' THEN [[orderTotal]] ELSE 0 END)",
                'lastAbandoned' => 'MAX([[abandonedAt]])',
                'active' => "SUM(CASE WHEN [[status]] = 'active' THEN 1 ELSE 0 END)",
            ])
            ->from([Table::RECOVERIES])
            ->groupBy(['customerId']);
        $skipStats = (new Query())
            ->select(['customerId', 'skipped' => 'COUNT(*)', 'lastSkipped' => 'MAX([[abandonedAt]])'])
            ->from([Table::SKIPPED_CARTS])
            ->where(['not', ['customerId' => null]])
            ->groupBy(['customerId']);

        $query = (new Query())
            ->from(['c' => Table::CUSTOMERS])
            ->leftJoin(['rs' => $recoveryStats], '[[rs.customerId]] = [[c.id]]')
            ->leftJoin(['ss' => $skipStats], '[[ss.customerId]] = [[c.id]]')
            ->leftJoin(['u' => '{{%users}}'], '[[u.id]] = [[c.userId]]');

        $status = $filters['status'] ?? 'all';

        // Addresses blocked before they ever had a cart only show in the Do Not Email view.
        if ($status === 'doNotEmail') {
            $query->andWhere(['c.status' => 'doNotEmail']);
        } else {
            $query->andWhere(['or', ['not', ['rs.customerId' => null]], ['not', ['ss.customerId' => null]]]);

            if (in_array($status, ['active', 'unsubscribed'], true)) {
                $query->andWhere(['c.status' => $status]);
            }
        }

        if ($search = trim((string)($filters['search'] ?? ''))) {
            $query->andWhere(['or', ['like', 'c.email', $search], ['like', 'u.fullName', $search]]);
        }

        $total = (int)(clone $query)->count();
        $cartsExpression = 'COALESCE([[rs.carts]], 0) + COALESCE([[ss.skipped]], 0)';
        $sortColumn = match ($sort) {
            'revenue' => new Expression('COALESCE([[rs.revenue]], 0)'),
            'recovered' => new Expression('COALESCE([[rs.recovered]], 0)'),
            'lastAbandoned' => new Expression('GREATEST(COALESCE([[rs.lastAbandoned]], [[ss.lastSkipped]]), COALESCE([[ss.lastSkipped]], [[rs.lastAbandoned]]))'),
            'customer' => 'c.email',
            default => new Expression($cartsExpression),
        };

        $rows = $query
            ->select([
                'c.id', 'c.email', 'c.status', 'c.statusSource', 'c.statusNote', 'c.statusChangedAt', 'c.userId',
                'name' => 'u.fullName',
                'carts' => new Expression($cartsExpression),
                'recovered' => new Expression('COALESCE([[rs.recovered]], 0)'),
                'revenue' => new Expression('COALESCE([[rs.revenue]], 0)'),
                'active' => new Expression('COALESCE([[rs.active]], 0)'),
                'lastAbandoned' => new Expression('GREATEST(COALESCE([[rs.lastAbandoned]], [[ss.lastSkipped]]), COALESCE([[ss.lastSkipped]], [[rs.lastAbandoned]]))'),
            ])
            ->orderBy($sortColumn instanceof Expression
                ? [new Expression($sortColumn->expression . ($direction === 'asc' ? ' ASC' : ' DESC')), 'c.id' => SORT_DESC]
                : [$sortColumn => $direction === 'asc' ? SORT_ASC : SORT_DESC, 'c.id' => SORT_DESC])
            ->offset(max(0, $page - 1) * $pageSize)
            ->limit($pageSize)
            ->all();

        return ['rows' => $rows, 'total' => $total];
    }

    public function getCustomerCounts(): array
    {
        $withCarts = (new Query())->select(['customerId'])->from([Table::RECOVERIES])
            ->union((new Query())->select(['customerId'])->from([Table::SKIPPED_CARTS])->where(['not', ['customerId' => null]]));
        $counts = (new Query())
            ->select(['status', 'count' => 'COUNT(*)'])
            ->from([Table::CUSTOMERS])
            ->where(['id' => $withCarts])
            ->groupBy(['status'])
            ->pairs();

        return [
            'all' => array_sum(array_map('intval', $counts)),
            'active' => (int)($counts['active'] ?? 0),
            'unsubscribed' => (int)($counts['unsubscribed'] ?? 0),
            'doNotEmail' => (int)(new Query())->from([Table::CUSTOMERS])->where(['status' => 'doNotEmail'])->count(),
        ];
    }

    public function getCustomerHistory(int $customerId): array
    {
        $recoveries = (new Query())
            ->select(['kind' => new Expression("'recovery'"), 'id', 'orderId', 'sequenceId', 'status', 'reason' => 'endReason', 'cartTotal', 'currency', 'abandonedAt', 'orderTotal'])
            ->from([Table::RECOVERIES])
            ->where(['customerId' => $customerId]);
        $skipped = (new Query())
            ->select(['kind' => new Expression("'skipped'"), 'id', 'orderId', 'sequenceId' => new Expression('NULL'), 'status' => new Expression("'skipped'"), 'reason', 'cartTotal', 'currency', 'abandonedAt', 'orderTotal' => new Expression('NULL')])
            ->from([Table::SKIPPED_CARTS])
            ->where(['customerId' => $customerId]);

        return (new Query())->from(['u' => $recoveries->union($skipped, true)])->orderBy(['abandonedAt' => SORT_DESC])->all();
    }


    // Overview
    // =========================================================================

    public function getRightNow(): array
    {
        $active = (new Query())
            ->select(['count' => 'COUNT(*)', 'value' => 'SUM([[cartTotal]])'])
            ->from([Table::RECOVERIES])
            ->where(['status' => Recovery::STATUS_ACTIVE])
            ->one();
        $scheduled = (new Query())
            ->from(['a' => Table::ATTEMPTS])
            ->innerJoin(['r' => Table::RECOVERIES], '[[r.id]] = [[a.recoveryId]]')
            ->where(['a.status' => Attempt::STATUS_SCHEDULED, 'r.status' => Recovery::STATUS_ACTIVE])
            ->andWhere(['<=', 'a.dueAt', Db::prepareDateForDb(new DateTime('+24 hours'))])
            ->count();
        $failed = (new Query())
            ->from([Table::ATTEMPTS])
            ->where(['status' => Attempt::STATUS_FAILED])
            ->andWhere(['>=', 'dateUpdated', Db::prepareDateForDb(new DateTime('-7 days'))])
            ->count();

        return [
            'inRecovery' => (int)$active['count'],
            'value' => (float)$active['value'],
            'scheduled' => (int)$scheduled,
            'failed' => (int)$failed,
        ];
    }

    /**
     * Headline numbers for a period and the one before it.
     */
    public function getSummary(DateTime $start, DateTime $end): array
    {
        $recoveries = (new Query())
            ->select([
                'entered' => 'COUNT(*)',
                'recovered' => "SUM(CASE WHEN [[status]] = 'recovered' THEN 1 ELSE 0 END)",
                'revenue' => "SUM(CASE WHEN [[status]] = 'recovered' THEN [[orderTotal]] ELSE 0 END)",
                'firstOrders' => "SUM(CASE WHEN [[status]] = 'recovered' AND [[isFirstOrder]] = TRUE THEN 1 ELSE 0 END)",
                'clicked' => "SUM(CASE WHEN [[status]] = 'recovered' AND [[clickedBeforeOrder]] = TRUE THEN 1 ELSE 0 END)",
                'value' => 'SUM([[cartTotal]])',
            ])
            ->from([Table::RECOVERIES])
            ->where(['>=', 'abandonedAt', Db::prepareDateForDb($start)])
            ->andWhere(['<', 'abandonedAt', Db::prepareDateForDb($end)])
            ->one();
        // Carts from blocked domains are usually bots or test orders, so they stay out of the Overview's totals.
        $skipped = (new Query())
            ->select([
                'count' => "SUM(CASE WHEN [[reason]] = :blocked THEN 0 ELSE 1 END)",
                'value' => "SUM(CASE WHEN [[reason]] = :blocked THEN 0 ELSE [[cartTotal]] END)",
                'blocked' => "SUM(CASE WHEN [[reason]] = :blocked THEN 1 ELSE 0 END)",
            ])
            ->from([Table::SKIPPED_CARTS])
            ->where(['>=', 'abandonedAt', Db::prepareDateForDb($start)])
            ->andWhere(['<', 'abandonedAt', Db::prepareDateForDb($end)])
            ->addParams([':blocked' => SkippedCart::REASON_BLOCKED_DOMAIN])
            ->one();
        $emailed = (int)(new Query())
            ->from(['r' => Table::RECOVERIES])
            ->where(['>=', 'r.abandonedAt', Db::prepareDateForDb($start)])
            ->andWhere(['<', 'r.abandonedAt', Db::prepareDateForDb($end)])
            ->andWhere(['exists', (new Query())->from(['a' => Table::ATTEMPTS])->where('[[a.recoveryId]] = [[r.id]]')->andWhere(['a.status' => Attempt::STATUS_SENT])])
            ->count();
        $clickedCarts = (int)(new Query())
            ->from(['r' => Table::RECOVERIES])
            ->where(['>=', 'r.abandonedAt', Db::prepareDateForDb($start)])
            ->andWhere(['<', 'r.abandonedAt', Db::prepareDateForDb($end)])
            ->andWhere(['exists', (new Query())->from(['v' => Table::VISITS])->where('[[v.recoveryId]] = [[r.id]]')->andWhere(['v.result' => 'restored'])])
            ->count();
        $emailsSent = (int)(new Query())
            ->from([Table::ATTEMPTS])
            ->where(['status' => Attempt::STATUS_SENT])
            ->andWhere(['>=', 'sentAt', Db::prepareDateForDb($start)])
            ->andWhere(['<', 'sentAt', Db::prepareDateForDb($end)])
            ->count();

        $entered = (int)$recoveries['entered'];
        $recovered = (int)$recoveries['recovered'];
        $revenue = (float)$recoveries['revenue'];

        return [
            'abandoned' => $entered + (int)$skipped['count'],
            'abandonedValue' => (float)$recoveries['value'] + (float)$skipped['value'],
            'skipped' => (int)$skipped['count'],
            'blockedDomains' => (int)$skipped['blocked'],
            'entered' => $entered,
            'emailed' => $emailed,
            'clicked' => $clickedCarts,
            'recovered' => $recovered,
            'revenue' => $revenue,
            'firstOrders' => (int)$recoveries['firstOrders'],
            'recoveredAfterClick' => (int)$recoveries['clicked'],
            'emailsSent' => $emailsSent,
            'rate' => $emailed ? $recovered / $emailed : 0,
            'revenuePerEmail' => $emailsSent ? $revenue / $emailsSent : 0,
            'averageOrder' => $recovered ? $revenue / $recovered : 0,
        ];
    }

    /**
     * Recovered revenue per day for the chart, keyed by Y-m-d with every day in the range present.
     */
    public function getDailyRevenue(DateTime $start, DateTime $end): array
    {
        $rows = (new Query())
            ->select(['recoveredAt', 'orderTotal'])
            ->from([Table::RECOVERIES])
            ->where(['status' => Recovery::STATUS_RECOVERED])
            ->andWhere(['>=', 'recoveredAt', Db::prepareDateForDb($start)])
            ->andWhere(['<', 'recoveredAt', Db::prepareDateForDb($end)])
            ->all();

        $timezone = new \DateTimeZone(\Craft::$app->getTimeZone());
        $days = [];

        for ($day = (clone $start)->setTimezone($timezone)->setTime(0, 0); $day < $end; $day->modify('+1 day')) {
            $days[$day->format('Y-m-d')] = 0.0;
        }

        foreach ($rows as $row) {
            $key = \craft\helpers\DateTimeHelper::toDateTime($row['recoveredAt'])->setTimezone($timezone)->format('Y-m-d');

            if (isset($days[$key])) {
                $days[$key] += (float)$row['orderTotal'];
            }
        }

        return $days;
    }

    public function getOutcomeReasons(DateTime $start, DateTime $end): array
    {
        $ended = (new Query())
            ->select(['reason' => 'endReason', 'count' => 'COUNT(*)'])
            ->from([Table::RECOVERIES])
            ->where(['status' => Recovery::STATUS_ENDED])
            ->andWhere(['>=', 'abandonedAt', Db::prepareDateForDb($start)])
            ->andWhere(['<', 'abandonedAt', Db::prepareDateForDb($end)])
            ->groupBy(['endReason'])
            ->pairs();
        $skipped = (new Query())
            ->select(['reason', 'count' => 'COUNT(*)'])
            ->from([Table::SKIPPED_CARTS])
            ->where(['>=', 'abandonedAt', Db::prepareDateForDb($start)])
            ->andWhere(['<', 'abandonedAt', Db::prepareDateForDb($end)])
            ->andWhere(['not', ['reason' => SkippedCart::REASON_BLOCKED_DOMAIN]])
            ->groupBy(['reason'])
            ->pairs();
        // Recoveries that ran out after an email failed, so a failure, not the customer, ended them.
        $failed = (int)(new Query())
            ->from(['r' => Table::RECOVERIES])
            ->where(['r.status' => Recovery::STATUS_ENDED, 'r.endReason' => [Recovery::END_FINISHED, Recovery::END_EXPIRED]])
            ->andWhere(['>=', 'r.abandonedAt', Db::prepareDateForDb($start)])
            ->andWhere(['<', 'r.abandonedAt', Db::prepareDateForDb($end)])
            ->andWhere(['exists', (new Query())->from(['a' => Table::ATTEMPTS])->where('[[a.recoveryId]] = [[r.id]]')->andWhere(['a.status' => Attempt::STATUS_FAILED])])
            ->count();

        return ['ended' => array_map('intval', $ended), 'skipped' => array_map('intval', $skipped), 'failed' => $failed];
    }

    public function getRecentRecoveries(int $limit = 6): array
    {
        return (new Query())
            ->select(['r.id', 'r.orderTotal', 'r.currency', 'r.recoveredAt', 'r.isFirstOrder', 'r.creditedAttemptId', 'r.clickedBeforeOrder', 'r.couponCode', 'c.email', 'name' => 'u.fullName', 'subject' => 'a.subject'])
            ->from(['r' => Table::RECOVERIES])
            ->leftJoin(['c' => Table::CUSTOMERS], '[[c.id]] = [[r.customerId]]')
            ->leftJoin(['u' => '{{%users}}'], '[[u.id]] = [[c.userId]]')
            ->leftJoin(['a' => Table::ATTEMPTS], '[[a.id]] = [[r.creditedAttemptId]]')
            ->where(['r.status' => Recovery::STATUS_RECOVERED])
            ->orderBy(['r.recoveredAt' => SORT_DESC])
            ->limit($limit)
            ->all();
    }

    public function countFrequentAbandoners(DateTime $since, int $minimum = 3): int
    {
        $recoveries = (new Query())->select(['customerId'])->from([Table::RECOVERIES])->where(['>=', 'abandonedAt', Db::prepareDateForDb($since)]);
        $skipped = (new Query())->select(['customerId'])->from([Table::SKIPPED_CARTS])->where(['>=', 'abandonedAt', Db::prepareDateForDb($since)])->andWhere(['not', ['customerId' => null]])->andWhere(['not', ['reason' => SkippedCart::REASON_BLOCKED_DOMAIN]]);

        return (int)(new Query())
            ->from(['x' => $recoveries->union($skipped, true)])
            ->groupBy(['customerId'])
            ->having(['>=', 'COUNT(*)', $minimum])
            ->count();
    }

    /**
     * Incomplete Commerce carts with an email address in a period: what emails could have reached, before any are sent.
     */
    public function getOpportunity(DateTime $since): array
    {
        $carts = \craft\commerce\elements\Order::find()
            ->isCompleted(false)
            ->hasLineItems(true)
            ->dateUpdated('>= ' . $since->format(DATE_ATOM))
            ->status(null);
        $withEmail = (clone $carts)->email(':notempty:');

        return [
            'carts' => (int)$carts->count(),
            'value' => (float)$carts->sum('[[commerce_orders.totalPrice]]'),
            'withEmail' => (int)$withEmail->count(),
        ];
    }

    // Private Methods
    // =========================================================================

    private function _applyListFilters(Query $query, string $alias, array $filters): void
    {
        if (!empty($filters['sequenceId']) && $alias === 'r') {
            $query->andWhere(["{$alias}.sequenceId" => (int)$filters['sequenceId']]);
        }

        if (!empty($filters['customerId'])) {
            $query->andWhere(["{$alias}.customerId" => (int)$filters['customerId']]);
        }

        if (!empty($filters['since'])) {
            $query->andWhere(['>=', "{$alias}.abandonedAt", Db::prepareDateForDb($filters['since'])]);
        }
    }
}
