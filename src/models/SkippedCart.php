<?php
namespace verbb\abandonedcart\models;

use Craft;
use craft\base\Model;

use DateTime;

class SkippedCart extends Model
{
    // Constants
    // =========================================================================

    public const REASON_NO_CONSENT = 'noConsent';
    public const REASON_DO_NOT_EMAIL = 'doNotEmail';
    public const REASON_UNSUBSCRIBED = 'unsubscribed';
    public const REASON_BLOCKED_DOMAIN = 'blockedDomain';
    public const REASON_NOT_RETURNING = 'notReturning';
    public const REASON_CONTACT_LIMIT = 'contactLimit';
    public const REASON_NO_SEQUENCE = 'noSequence';
    public const REASON_CANCELLED = 'cancelled';


    // Properties
    // =========================================================================

    public ?int $id = null;
    public ?int $orderId = null;
    public ?int $customerId = null;
    public ?int $siteId = null;
    public ?string $reason = null;
    public ?string $detail = null;
    public float $cartTotal = 0;
    public ?string $currency = null;
    public ?DateTime $abandonedAt = null;
    public ?DateTime $dateCreated = null;
    public ?DateTime $dateUpdated = null;
    public ?string $uid = null;


    // Public Methods
    // =========================================================================

    public static function reasonLabels(): array
    {
        return [
            self::REASON_NO_CONSENT => Craft::t('abandoned-cart', 'Didn’t opt in'),
            self::REASON_DO_NOT_EMAIL => Craft::t('abandoned-cart', 'On Do Not Email'),
            self::REASON_UNSUBSCRIBED => Craft::t('abandoned-cart', 'Unsubscribed'),
            self::REASON_BLOCKED_DOMAIN => Craft::t('abandoned-cart', 'Blocked domain'),
            self::REASON_NOT_RETURNING => Craft::t('abandoned-cart', 'Not a returning customer'),
            self::REASON_CONTACT_LIMIT => Craft::t('abandoned-cart', 'Contacted recently'),
            self::REASON_NO_SEQUENCE => Craft::t('abandoned-cart', 'No sequence was on'),
            self::REASON_CANCELLED => Craft::t('abandoned-cart', 'Excluded by a plugin'),
        ];
    }

    public function datetimeAttributes(): array
    {
        return [...parent::datetimeAttributes(), 'abandonedAt'];
    }

    public function getReasonLabel(): string
    {
        return self::reasonLabels()[$this->reason] ?? (string)$this->reason;
    }
}
