<?php
namespace verbb\abandonedcart\models;

use Craft;
use craft\base\Model;
use craft\helpers\App;
use craft\helpers\StringHelper;

class Settings extends Model
{
    // Constants
    // =========================================================================

    public const MIN_HISTORY_RETENTION_DAYS = 30;


    // Properties
    // =========================================================================

    public string $pluginName = 'Abandoned Carts';

    // Recovery Defaults. Every sequence uses these unless it overrides them. Durations are in minutes.
    public int $abandonedAfterMinutes = 60;
    public ?string $returnUrl = 'shop/cart';
    public int $recoveryWindowMinutes = 10080;
    public int $attributionWindowMinutes = 7200;
    public int $contactLimitMinutes = 10080;

    // Who can be emailed.
    public ?string $consentFieldHandle = null;
    public bool $returningCustomersOnly = false;
    public array $blockedDomains = [];

    // Customer pages and sending.
    public ?string $customerPageTemplate = null;
    public bool|string $deliveryPaused = false;
    public int $historyRetentionDays = 365;

    // Config file only.
    public int $maxEnrollmentsPerRun = 100;
    public bool $compatibilityMode = true;


    // Public Methods
    // =========================================================================

    public function setAttributes($values, $safeOnly = true): void
    {
        // Blocked domains arrive as one per line from the control panel.
        if (isset($values['blockedDomains']) && is_string($values['blockedDomains'])) {
            $values['blockedDomains'] = StringHelper::split($values['blockedDomains'], "\n");
        }

        parent::setAttributes($values, $safeOnly);
    }

    public function getReturnUrl(): ?string
    {
        return App::parseEnv($this->returnUrl);
    }

    public function getDeliveryPaused(): bool
    {
        return App::parseBooleanEnv($this->deliveryPaused) ?? false;
    }

    public function getBlockedDomains(): array
    {
        $domains = array_map(static fn(string $domain): string => strtolower(trim(ltrim(trim($domain), '@'))), $this->blockedDomains);

        return array_values(array_unique(array_filter($domains)));
    }


    // Protected Methods
    // =========================================================================

    protected function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['pluginName'], 'required'];
        $rules[] = [['abandonedAfterMinutes', 'recoveryWindowMinutes', 'attributionWindowMinutes', 'contactLimitMinutes'], 'integer', 'min' => 0];
        $rules[] = [['abandonedAfterMinutes'], 'integer', 'min' => 1];
        $rules[] = [['historyRetentionDays'], 'integer', 'min' => self::MIN_HISTORY_RETENTION_DAYS];
        $rules[] = [['maxEnrollmentsPerRun'], 'integer', 'min' => 1, 'max' => 1000];
        $rules[] = [['returnUrl', 'customerPageTemplate'], 'string', 'max' => 1024];
        $rules[] = [['consentFieldHandle'], 'string', 'max' => 255];

        return $rules;
    }
}
