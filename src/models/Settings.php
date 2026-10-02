<?php
namespace verbb\abandonedcart\models;

use craft\base\Model;
use craft\behaviors\EnvAttributeParserBehavior;
use craft\helpers\App;
use craft\helpers\StringHelper;

class Settings extends Model
{
    // Properties
    // =========================================================================

    public string $pluginName = 'Abandoned Carts';
    public ?string $passKey = null;
    public int|string|null $restoreExpiryHours = 48;
    public int|string|null $firstReminderDelay = 1;
    public int|string|null $secondReminderDelay = 12;
    public int|string|null $recipientCooldownHours = 24;
    public int|string|null $maxEnrollmentsPerRun = 100;
    public int|string|null $staleRecordRetentionDays = 90;
    public ?string $discountCode = null;
    public ?string $firstReminderTemplate = 'abandoned-cart/emails/first';
    public ?string $secondReminderTemplate = 'abandoned-cart/emails/second';
    public ?string $firstReminderSubject = 'You‘ve left some items in your cart';
    public ?string $secondReminderSubject = 'Your items are still waiting - don‘t miss out';
    public ?string $recoveryUrl = 'shop/cart';
    public bool|string|null $disableSecondReminder = false;
    public bool|string|null $previousOrderRequired = false;
    public ?string $blacklist = null;
    public bool $includeBlacklisted = true;


    // Public Methods
    // =========================================================================

    public function __construct($config = [])
    {
        // Handle legacy settings
        unset($config['testMode']);

        parent::__construct($config);
    }

    public function init(): void
    {
        if (empty($this->passKey)) {
            $this->passKey = StringHelper::randomString(15);
        }

        parent::init();
    }

    public function getPassKey(): ?string
    {
        return App::parseEnv($this->passKey);
    }

    public function getPluginName(): ?string
    {
        return App::parseEnv($this->pluginName);
    }

    public function getDisableSecondReminder(): ?bool
    {
        return App::parseBooleanEnv($this->disableSecondReminder);
    }

    public function getRestoreExpiryHours(): ?string
    {
        return App::parseEnv($this->restoreExpiryHours);
    }

    public function getFirstReminderDelay(): ?string
    {
        return App::parseEnv($this->firstReminderDelay);
    }

    public function getSecondReminderDelay(): ?string
    {
        return App::parseEnv($this->secondReminderDelay);
    }

    public function getRecipientCooldownHours(): int
    {
        return $this->_getPositiveIntegerSetting($this->recipientCooldownHours, 24, 8760);
    }

    public function getMaxEnrollmentsPerRun(): int
    {
        return $this->_getPositiveIntegerSetting($this->maxEnrollmentsPerRun, 100, 1000);
    }

    public function getStaleRecordRetentionDays(): int
    {
        return $this->_getPositiveIntegerSetting($this->staleRecordRetentionDays, 90, 3650);
    }

    public function getFirstReminderTemplate(): ?string
    {
        return App::parseEnv($this->firstReminderTemplate);
    }

    public function getFirstReminderSubject(): ?string
    {
        return App::parseEnv($this->firstReminderSubject);
    }

    public function getSecondReminderTemplate(): ?string
    {
        return App::parseEnv($this->secondReminderTemplate);
    }

    public function getSecondReminderSubject(): ?string
    {
        return App::parseEnv($this->secondReminderSubject);
    }

    public function getDiscountCode(): ?string
    {
        return App::parseEnv($this->discountCode);
    }

    public function getRecoveryUrl(): ?string
    {
        return App::parseEnv($this->recoveryUrl);
    }

    public function getBlacklist(): array
    {
        $items = App::parseEnv($this->blacklist);

        if (is_string($items)) {
            $items = array_map('trim', explode(',', $items));
        }

        if (!is_array($items)) {
            return [];
        }

        // Normalize: trim, lowercase, remove empties, dedupe
        return array_values(array_unique(array_filter(array_map('strtolower', array_map('trim', $items)))));
    }


    // Protected Methods
    // =========================================================================

    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['pluginName'], 'trim'];
        $rules[] = [['pluginName', 'restoreExpiryHours', 'firstReminderDelay', 'secondReminderDelay', 'recipientCooldownHours', 'maxEnrollmentsPerRun', 'staleRecordRetentionDays', 'firstReminderTemplate', 'secondReminderTemplate', 'firstReminderSubject', 'secondReminderSubject', 'recoveryUrl', 'passKey'], 'required'];
        $rules[] = [['restoreExpiryHours'], 'integer'];
        $rules[] = [['firstReminderDelay'], 'integer', 'min' => 0];
        $rules[] = [['secondReminderDelay'], 'integer', 'min' => 0];
        $rules[] = [['recipientCooldownHours'], 'integer', 'min' => 1, 'max' => 8760];
        $rules[] = [['maxEnrollmentsPerRun'], 'integer', 'min' => 1, 'max' => 1000];
        $rules[] = [['staleRecordRetentionDays'], 'integer', 'min' => 1, 'max' => 3650];

        return $rules;
    }

    protected function defineBehaviors(): array
    {
        return [
            'parser' => [
                'class' => EnvAttributeParserBehavior::class,
                'attributes' => [
                    'passKey',
                    'pluginName',
                    'disableSecondReminder',
                    'restoreExpiryHours',
                    'firstReminderDelay',
                    'secondReminderDelay',
                    'recipientCooldownHours',
                    'maxEnrollmentsPerRun',
                    'staleRecordRetentionDays',
                    'firstReminderTemplate',
                    'firstReminderSubject',
                    'secondReminderTemplate',
                    'secondReminderSubject',
                    'discountCode',
                    'recoveryUrl',
                    'blacklist',
                ],
            ],
        ];
    }


    // Private Methods
    // =========================================================================

    private function _getPositiveIntegerSetting(int|string|null $value, int $default, int $maximum): int
    {
        $value = App::parseEnv($value);

        if (is_string($value) && preg_match('/^[1-9][0-9]*$/D', $value)) {
            $value = filter_var($value, FILTER_VALIDATE_INT);
        }

        if (!is_int($value) || $value < 1) {
            return $default;
        }

        return min($value, $maximum);
    }
}
