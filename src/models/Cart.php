<?php
namespace verbb\abandonedcart\models;

use verbb\abandonedcart\AbandonedCart;

use Craft;
use craft\base\Model;
use craft\elements\User;
use craft\helpers\App;
use craft\helpers\StringHelper;

use DateInterval;
use DateTime;

use craft\commerce\Plugin as Commerce;
use craft\commerce\elements\Order;

class Cart extends Model
{
    // Constants
    // =========================================================================

    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_RECOVERED = 'recovered';
    public const STATUS_SENT = 'sent';
    public const STATUS_EXPIRED = 'expired';


    // Properties
    // =========================================================================

    public ?int $id = null;
    public ?int $orderId = null;
    public ?string $email = null;
    public bool $clicked = false;
    public bool $isScheduled = false;
    public bool $firstReminder = false;
    public bool $secondReminder = false;
    public bool $isRecovered = false;
    public bool $isSent = false;
    public ?DateTime $dateLastSent = null;
    public ?DateTime $dateCreated = null;
    public ?DateTime $dateUpdated = null;
    public ?string $uid = null;


    // Public Methods
    // =========================================================================

    public function getOrder(): ?Order
    {
        if (!$this->orderId) {
            return null;
        }

        return Commerce::getInstance()->getOrders()->getOrderById($this->orderId);
    }

    public function getUser(): ?User
    {
        if (!$this->email) {
            return null;
        }

        return User::find()
            ->email($this->email)
            ->status(null)
            ->one();
    }

    public function getStatus(): string
    {
        if ($this->isScheduled && !$this->isRecovered) {
            return self::STATUS_SCHEDULED;
        }

        if ($this->isRecovered) {
            return self::STATUS_RECOVERED;
        }

        $expiresAt = $this->getRecoveryExpiresAt();

        if (!$expiresAt && $this->dateUpdated) {
            $expiresAt = $this->_getExpiresAt($this->dateUpdated);
        }

        if ($expiresAt && new DateTime() >= $expiresAt) {
            return self::STATUS_EXPIRED;
        }

        if ($this->isSent) {
            return self::STATUS_SENT;
        }

        return self::STATUS_SCHEDULED;
    }

    public function getRecoveryExpiresAt(): ?DateTime
    {
        if (!$this->dateLastSent) {
            return null;
        }

        return $this->_getExpiresAt($this->dateLastSent);
    }

    public function getIsRecoveryAvailable(?DateTime $now = null): bool
    {
        $expiresAt = $this->getRecoveryExpiresAt();

        return $expiresAt !== null && ($now ?? new DateTime()) < $expiresAt;
    }


    // Private Methods
    // =========================================================================

    private function _getExpiresAt(DateTime $anchor): DateTime
    {
        $expiry = AbandonedCart::$plugin->getSettings()->getRestoreExpiryHours();
        $expiresAt = clone $anchor;
        $expiresAt->add(new DateInterval("PT{$expiry}H"));

        return $expiresAt;
    }
}
