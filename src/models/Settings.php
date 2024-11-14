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
    public ?string $discountCode = null;
    public ?string $firstReminderTemplate = 'abandoned-cart/emails/first';
    public ?string $secondReminderTemplate = 'abandoned-cart/emails/second';
    public ?string $firstReminderSubject = 'You‘ve left some items in your cart';
    public ?string $secondReminderSubject = 'Your items are still waiting - don‘t miss out';
    public ?string $recoveryUrl = 'shop/cart';
    public bool|string|null $disableSecondReminder = false;
    public ?string $blacklist = null;


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

    public function getBlacklist(): ?string
    {
        return App::parseEnv($this->blacklist);
    }


    // Protected Methods
    // =========================================================================

    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['pluginName', 'restoreExpiryHours', 'firstReminderDelay', 'secondReminderDelay', 'firstReminderTemplate', 'secondReminderTemplate', 'firstReminderSubject', 'secondReminderSubject', 'recoveryUrl', 'passKey'], 'required'];
        $rules[] = [['restoreExpiryHours'], 'integer', 'min' => 24, 'max' => '168']; // Atleast 24hrs
        $rules[] = [['firstReminderDelay'], 'integer', 'min' => 1, 'max' => 24]; // 1hr +
        $rules[] = [['secondReminderDelay'], 'integer', 'min' => 12, 'max' => 48]; // prevent spam

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
}
