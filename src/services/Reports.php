<?php
namespace verbb\abandonedcart\services;

use verbb\abandonedcart\db\Table;
use verbb\abandonedcart\models\Attempt;
use verbb\abandonedcart\models\Email;
use verbb\abandonedcart\models\Recovery;

use craft\base\Component;
use craft\db\Query;
use craft\helpers\Db;

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
}
