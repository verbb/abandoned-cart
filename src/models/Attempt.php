<?php
namespace verbb\abandonedcart\models;

use verbb\abandonedcart\AbandonedCart;

use Craft;
use craft\base\Model;

use DateTime;

class Attempt extends Model
{
    // Constants
    // =========================================================================

    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_SENDING = 'sending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    public const MAX_TRIES = 3;


    // Properties
    // =========================================================================

    public ?int $id = null;
    public ?int $recoveryId = null;
    public ?int $emailId = null;
    public int $position = 1;
    public string $status = self::STATUS_SCHEDULED;
    public ?DateTime $dueAt = null;
    public ?DateTime $sentAt = null;
    public ?string $subject = null;
    public int $tries = 0;
    public ?string $leaseToken = null;
    public ?DateTime $leaseExpiresAt = null;
    public ?string $failureReason = null;
    public ?DateTime $dateCreated = null;
    public ?DateTime $dateUpdated = null;
    public ?string $uid = null;


    // Public Methods
    // =========================================================================

    public static function statusLabels(): array
    {
        return [
            self::STATUS_SCHEDULED => Craft::t('abandoned-cart', 'Scheduled'),
            self::STATUS_SENDING => Craft::t('abandoned-cart', 'Sending'),
            self::STATUS_SENT => Craft::t('abandoned-cart', 'Sent'),
            self::STATUS_FAILED => Craft::t('abandoned-cart', 'Failed'),
            self::STATUS_CANCELLED => Craft::t('abandoned-cart', 'Not sent'),
        ];
    }

    public function datetimeAttributes(): array
    {
        return [...parent::datetimeAttributes(), 'dueAt', 'sentAt', 'leaseExpiresAt'];
    }

    public function getEmail(): ?Email
    {
        return $this->emailId ? AbandonedCart::$plugin->getEmails()->getEmailById($this->emailId) : null;
    }

    public function getStatusLabel(): string
    {
        return self::statusLabels()[$this->status] ?? $this->status;
    }
}
