<?php
namespace verbb\abandonedcart\services;

use verbb\abandonedcart\db\Table;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;
use craft\helpers\Json;

/**
 * The history shown on a cart: what happened and when, written as it happens.
 */
class Activities extends Component
{
    // Constants
    // =========================================================================

    public const TYPE_ENROLLED = 'enrolled';
    public const TYPE_RESCHEDULED = 'rescheduled';
    public const TYPE_SENT = 'sent';
    public const TYPE_FAILED = 'failed';
    public const TYPE_CANCELLED = 'cancelled';
    public const TYPE_CLICKED = 'clicked';
    public const TYPE_RECOVERED = 'recovered';
    public const TYPE_ENDED = 'ended';
    public const TYPE_SEND_NOW = 'sendNow';
    public const TYPE_IMPORTED = 'imported';
    public const TYPE_RETRIED = 'retried';
    public const TYPE_TAKEN_OVER = 'takenOver';


    // Public Methods
    // =========================================================================

    public function log(int $recoveryId, string $type, ?int $attemptId = null, array $data = []): void
    {
        Db::insert(Table::ACTIVITIES, [
            'recoveryId' => $recoveryId,
            'attemptId' => $attemptId,
            'type' => $type,
            'data' => $data ? Json::encode($data) : null,
        ]);
    }

    public function getActivitiesByRecoveryId(int $recoveryId): array
    {
        $rows = (new Query())
            ->select(['id', 'attemptId', 'type', 'data', 'dateCreated'])
            ->from([Table::ACTIVITIES])
            ->where(['recoveryId' => $recoveryId])
            ->orderBy(['dateCreated' => SORT_DESC, 'id' => SORT_DESC])
            ->all();

        return array_map(static fn(array $row): array => [
            'id' => (int)$row['id'],
            'attemptId' => $row['attemptId'] ? (int)$row['attemptId'] : null,
            'type' => $row['type'],
            'data' => $row['data'] ? Json::decode($row['data']) : [],
            'date' => DateTimeHelper::toDateTime($row['dateCreated']),
        ], $rows);
    }

    public function describe(array $activity): string
    {
        $data = $activity['data'];

        return match ($activity['type']) {
            self::TYPE_ENROLLED => Craft::t('abandoned-cart', 'Entered {sequence}', ['sequence' => $data['sequence'] ?? Craft::t('abandoned-cart', 'a sequence')]),
            self::TYPE_RESCHEDULED => Craft::t('abandoned-cart', 'Cart changed, so the next email moved later'),
            self::TYPE_SENT => Craft::t('abandoned-cart', 'Email {position} sent: “{subject}”', ['position' => $data['position'] ?? '', 'subject' => $data['subject'] ?? '']),
            self::TYPE_FAILED => Craft::t('abandoned-cart', 'Email {position} couldn’t be sent: {reason}', ['position' => $data['position'] ?? '', 'reason' => $data['reason'] ?? '']),
            self::TYPE_CANCELLED => Craft::t('abandoned-cart', 'Email {position} wasn’t sent: {reason}', ['position' => $data['position'] ?? '', 'reason' => $data['reason'] ?? '']),
            self::TYPE_CLICKED => Craft::t('abandoned-cart', 'Cart restored from email {position}’s link', ['position' => $data['position'] ?? '']),
            self::TYPE_RECOVERED => Craft::t('abandoned-cart', 'Order {number} completed, credited to email {position}', ['number' => $data['number'] ?? '', 'position' => $data['position'] ?? '']),
            self::TYPE_ENDED => Craft::t('abandoned-cart', 'Recovery ended: {reason}', ['reason' => $data['reason'] ?? '']),
            self::TYPE_SEND_NOW => Craft::t('abandoned-cart', 'Next email sent manually'),
            self::TYPE_RETRIED => Craft::t('abandoned-cart', 'Email {position} retried by staff', ['position' => $data['position'] ?? '']),
            self::TYPE_TAKEN_OVER => Craft::t('abandoned-cart', 'Moved to the customer’s newer cart, order {number}', ['number' => $data['number'] ?? '']),
            self::TYPE_IMPORTED => Craft::t('abandoned-cart', 'Imported from 4.x reminder history'),
            default => $activity['type'],
        };
    }
}
