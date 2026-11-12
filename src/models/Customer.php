<?php
namespace verbb\abandonedcart\models;

use Craft;
use craft\base\Model;
use craft\elements\User;
use craft\helpers\UrlHelper;

use DateTime;

class Customer extends Model
{
    // Constants
    // =========================================================================

    public const STATUS_ACTIVE = 'active';
    public const STATUS_UNSUBSCRIBED = 'unsubscribed';
    public const STATUS_DO_NOT_EMAIL = 'doNotEmail';

    public const SOURCE_UNSUBSCRIBE = 'unsubscribe';
    public const SOURCE_STAFF = 'staff';
    public const SOURCE_CHECKOUT = 'checkout';
    public const SOURCE_UPGRADE = 'upgrade';
    public const SOURCE_ACCOUNT = 'account';


    // Properties
    // =========================================================================

    public ?int $id = null;
    public ?string $email = null;
    public ?int $userId = null;
    public string $status = self::STATUS_ACTIVE;
    public ?string $statusSource = null;
    public ?string $statusNote = null;
    public ?int $statusChangedById = null;
    public ?DateTime $statusChangedAt = null;
    // The status, source, note, who and when from before Do Not Email, so removing it restores them.
    public ?array $previousStatus = null;
    public ?DateTime $dateCreated = null;
    public ?DateTime $dateUpdated = null;
    public ?string $uid = null;


    // Public Methods
    // =========================================================================

    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public function datetimeAttributes(): array
    {
        $attributes = parent::datetimeAttributes();
        $attributes[] = 'statusChangedAt';

        return $attributes;
    }

    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl('abandoned-cart/customers/' . $this->id);
    }

    public function getUser(): ?User
    {
        return $this->userId ? Craft::$app->getUsers()->getUserById($this->userId) : null;
    }

    public function getDomain(): string
    {
        return (string)substr((string)strrchr((string)$this->email, '@'), 1);
    }

    public function getIsEmailable(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }


    // Protected Methods
    // =========================================================================

    protected function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['email', 'status'], 'required'];
        $rules[] = [['email'], 'email'];
        $rules[] = [['email', 'statusSource'], 'string', 'max' => 255];
        $rules[] = [['status'], 'in', 'range' => [self::STATUS_ACTIVE, self::STATUS_UNSUBSCRIBED, self::STATUS_DO_NOT_EMAIL]];

        return $rules;
    }
}
