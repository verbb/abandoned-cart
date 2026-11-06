<?php
namespace verbb\abandonedcart\models;

use verbb\abandonedcart\AbandonedCart;

use Craft;
use craft\base\Model;
use craft\helpers\UrlHelper;

use DateTime;

use craft\commerce\elements\Order;

class Recovery extends Model
{
    // Constants
    // =========================================================================

    public const STATUS_ACTIVE = 'active';
    public const STATUS_RECOVERED = 'recovered';
    public const STATUS_ENDED = 'ended';

    public const END_FINISHED = 'finished';
    public const END_EXPIRED = 'expired';
    public const END_NEWER_CART = 'newerCart';
    public const END_UNSUBSCRIBED = 'unsubscribed';
    public const END_DO_NOT_EMAIL = 'doNotEmail';
    public const END_COMPLETED_BEFORE_EMAIL = 'completedBeforeEmail';
    public const END_COMPLETED_OUTSIDE_WINDOW = 'completedOutsideWindow';
    public const END_CART_EMPTIED = 'cartEmptied';
    public const END_ORDER_REMOVED = 'orderRemoved';
    public const END_STOPPED = 'stopped';
    public const END_IMPORTED = 'imported';


    // Properties
    // =========================================================================

    public ?int $id = null;
    public ?int $orderId = null;
    public ?int $customerId = null;
    public ?int $siteId = null;
    public ?int $sequenceId = null;
    public ?int $sequenceVersionId = null;
    public string $status = self::STATUS_ACTIVE;
    public ?string $endReason = null;
    public float $cartTotal = 0;
    public ?string $currency = null;
    public int $attributionWindowMinutes = 7200;
    public ?DateTime $abandonedAt = null;
    public ?DateTime $expiresAt = null;
    public ?DateTime $endedAt = null;
    public ?DateTime $recoveredAt = null;
    public ?int $creditedAttemptId = null;
    public bool $clickedBeforeOrder = false;
    public ?float $orderTotal = null;
    public ?bool $isFirstOrder = null;
    public ?string $couponCode = null;
    public ?DateTime $dateCreated = null;
    public ?DateTime $dateUpdated = null;
    public ?string $uid = null;


    // Public Methods
    // =========================================================================

    public static function endReasonLabels(): array
    {
        return [
            self::END_FINISHED => Craft::t('abandoned-cart', 'Emails finished'),
            self::END_EXPIRED => Craft::t('abandoned-cart', 'Recovery period ended'),
            self::END_NEWER_CART => Craft::t('abandoned-cart', 'Started a newer cart'),
            self::END_UNSUBSCRIBED => Craft::t('abandoned-cart', 'Unsubscribed'),
            self::END_DO_NOT_EMAIL => Craft::t('abandoned-cart', 'Added to Do Not Email'),
            self::END_COMPLETED_BEFORE_EMAIL => Craft::t('abandoned-cart', 'Ordered before any email'),
            self::END_COMPLETED_OUTSIDE_WINDOW => Craft::t('abandoned-cart', 'Ordered after the attribution window'),
            self::END_CART_EMPTIED => Craft::t('abandoned-cart', 'Cart emptied'),
            self::END_ORDER_REMOVED => Craft::t('abandoned-cart', 'Cart deleted'),
            self::END_STOPPED => Craft::t('abandoned-cart', 'Stopped by staff'),
            self::END_IMPORTED => Craft::t('abandoned-cart', 'Imported from 4.x'),
        ];
    }

    public function datetimeAttributes(): array
    {
        return [...parent::datetimeAttributes(), 'abandonedAt', 'expiresAt', 'endedAt', 'recoveredAt'];
    }

    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl('abandoned-cart/carts/' . $this->id);
    }

    public function getOrder(): ?Order
    {
        return $this->orderId ? Order::find()->id($this->orderId)->status(null)->one() : null;
    }

    public function getCustomer(): ?Customer
    {
        return $this->customerId ? AbandonedCart::$plugin->getCustomers()->getCustomerById($this->customerId) : null;
    }

    public function getSequence(): ?Sequence
    {
        return $this->sequenceId ? AbandonedCart::$plugin->getSequences()->getSequenceById($this->sequenceId) : null;
    }

    public function getAttempts(): array
    {
        return $this->id ? AbandonedCart::$plugin->getAttempts()->getAttemptsByRecoveryId($this->id) : [];
    }

    public function getEndReasonLabel(): ?string
    {
        return $this->endReason ? (self::endReasonLabels()[$this->endReason] ?? $this->endReason) : null;
    }

    public function getIsActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
