<?php
namespace verbb\abandonedcart\models;

use verbb\abandonedcart\AbandonedCart;

use Craft;
use craft\base\Model;
use craft\helpers\App;
use craft\helpers\UrlHelper;
use craft\validators\HandleValidator;

use DateTime;

class Sequence extends Model
{
    // Constants
    // =========================================================================

    public const AUDIENCE_ALL = 'all';
    public const AUDIENCE_CART_VALUE = 'cartValue';
    public const AUDIENCE_RETURNING = 'returning';
    public const AUDIENCE_FIRST_TIME = 'firstTime';
    public const AUDIENCE_PRODUCTS = 'products';
    public const AUDIENCE_CUSTOM = 'custom';


    // Properties
    // =========================================================================

    public ?int $id = null;
    public ?string $name = null;
    public ?string $handle = null;
    public int $sortOrder = 0;
    public bool $isFallback = false;
    public bool $enabled = false;
    public array $siteIds = [];
    public string $audienceType = self::AUDIENCE_ALL;
    public array $audienceSettings = [];

    // Overrides of the Recovery Defaults. Null uses the plugin setting.
    public ?int $abandonedAfterMinutes = null;
    public ?int $recoveryWindowMinutes = null;
    public ?int $attributionWindowMinutes = null;
    public ?int $contactLimitMinutes = null;
    public ?string $returnUrl = null;

    public ?DateTime $enrollmentStartedAt = null;
    public ?DateTime $dateCreated = null;
    public ?DateTime $dateUpdated = null;
    public ?string $uid = null;


    // Public Methods
    // =========================================================================

    public static function audienceTypes(): array
    {
        return [
            self::AUDIENCE_ALL,
            self::AUDIENCE_CART_VALUE,
            self::AUDIENCE_RETURNING,
            self::AUDIENCE_FIRST_TIME,
            self::AUDIENCE_PRODUCTS,
            self::AUDIENCE_CUSTOM,
        ];
    }

    public function datetimeAttributes(): array
    {
        $attributes = parent::datetimeAttributes();
        $attributes[] = 'enrollmentStartedAt';

        return $attributes;
    }

    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl('abandoned-cart/sequences/' . $this->id);
    }

    public function getEmails(): array
    {
        if (!$this->id) {
            return [];
        }

        return AbandonedCart::$plugin->getEmails()->getEmailsBySequenceId($this->id);
    }

    public function getAbandonedAfterMinutes(): int
    {
        return $this->abandonedAfterMinutes ?? AbandonedCart::$plugin->getSettings()->abandonedAfterMinutes;
    }

    public function getRecoveryWindowMinutes(): int
    {
        return $this->recoveryWindowMinutes ?? AbandonedCart::$plugin->getSettings()->recoveryWindowMinutes;
    }

    public function getAttributionWindowMinutes(): int
    {
        return $this->attributionWindowMinutes ?? AbandonedCart::$plugin->getSettings()->attributionWindowMinutes;
    }

    public function getContactLimitMinutes(): int
    {
        return $this->contactLimitMinutes ?? AbandonedCart::$plugin->getSettings()->contactLimitMinutes;
    }

    public function getReturnUrl(): ?string
    {
        return $this->returnUrl !== null ? App::parseEnv($this->returnUrl) : AbandonedCart::$plugin->getSettings()->getReturnUrl();
    }

    public function hasOverrides(): bool
    {
        return $this->abandonedAfterMinutes !== null
            || $this->recoveryWindowMinutes !== null
            || $this->attributionWindowMinutes !== null
            || $this->contactLimitMinutes !== null
            || $this->returnUrl !== null;
    }

    public function getSnapshot(array $emails): array
    {
        // What a cart keeps once it's in this sequence: who it was for, its timing and its email schedule. Wording stays live.
        return [
            'siteIds' => $this->siteIds,
            'audienceType' => $this->audienceType,
            'audienceSettings' => $this->audienceSettings,
            'abandonedAfterMinutes' => $this->abandonedAfterMinutes,
            'recoveryWindowMinutes' => $this->recoveryWindowMinutes,
            'attributionWindowMinutes' => $this->attributionWindowMinutes,
            'contactLimitMinutes' => $this->contactLimitMinutes,
            'returnUrl' => $this->returnUrl,
            'emails' => array_map(static fn(Email $email): array => [
                'id' => $email->id,
                'position' => $email->position,
                'delayMinutes' => $email->delayMinutes,
            ], $emails),
        ];
    }


    // Protected Methods
    // =========================================================================

    protected function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['name', 'handle', 'audienceType'], 'required'];
        $rules[] = [['name', 'handle'], 'string', 'max' => 255];
        $rules[] = [['returnUrl'], 'string', 'max' => 1024];
        $rules[] = [['handle'], HandleValidator::class];
        // Plugins can register more audiences, so the registered list is the one that counts.
        $rules[] = [['audienceType'], 'in', 'range' => fn(): array => array_keys(AbandonedCart::$plugin->getAudiences()->getAllAudiences())];
        $rules[] = [['abandonedAfterMinutes'], 'integer', 'min' => 1];
        $rules[] = [['recoveryWindowMinutes', 'attributionWindowMinutes', 'contactLimitMinutes'], 'integer', 'min' => 0];
        $rules[] = [['audienceType'], function(string $attribute): void {
            if ($this->isFallback && $this->audienceType !== self::AUDIENCE_ALL) {
                $this->addError($attribute, Craft::t('abandoned-cart', 'Everyone Else always takes every cart that reaches it.'));
            }
        }];
        $rules[] = [['isFallback'], function(string $attribute): void {
            $fallback = AbandonedCart::$plugin->getSequences()->getFallbackSequence();

            if ($this->isFallback && $fallback && $fallback->id !== $this->id) {
                $this->addError($attribute, Craft::t('abandoned-cart', 'There can only be one Everyone Else sequence.'));
            }
        }];
        $rules[] = [['handle'], function(string $attribute): void {
            $existing = AbandonedCart::$plugin->getSequences()->getSequenceByHandle((string)$this->handle);

            if ($existing && $existing->id !== $this->id) {
                $this->addError($attribute, Craft::t('abandoned-cart', 'This handle is already in use.'));
            }
        }];

        return $rules;
    }
}
