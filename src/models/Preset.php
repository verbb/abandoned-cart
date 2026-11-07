<?php
namespace verbb\abandonedcart\models;

use Craft;
use craft\base\Model;
use craft\helpers\UrlHelper;
use craft\validators\HandleValidator;

/**
 * A starting point for a new sequence. Presets are managed under Settings → Sequence Presets and kept in project
 * config; plugins can add, change or remove them with `Presets::EVENT_REGISTER_PRESETS`.
 */
class Preset extends Model
{
    // Constants
    // =========================================================================

    public const SCENARIO_SETTINGS = 'settings';


    // Properties
    // =========================================================================

    public ?string $uid = null;
    public ?string $handle = null;
    public ?string $name = null;
    public ?string $description = null;
    public string $audienceType = Sequence::AUDIENCE_ALL;
    public array $audienceSettings = [];
    public int $sortOrder = 0;

    // Each email is an array of Email attributes, plus an optional `emailTemplate` handle and an optional
    // `offersDiscount` flag that marks the email meant to carry a discount code in the preset picker.
    public array $emails = [];

    // Overrides of the Recovery Defaults, keyed by Sequence attribute.
    public array $overrides = [];

    // Whether the preset is kept in project config, so it can be changed under Settings. Presets that a plugin adds
    // with `Presets::EVENT_REGISTER_PRESETS` can't be.
    public bool $isEditable = false;


    // Public Methods
    // =========================================================================

    public function getCpEditUrl(): ?string
    {
        return $this->isEditable && $this->uid ? UrlHelper::cpUrl('abandoned-cart/settings/presets/' . $this->uid) : null;
    }

    /**
     * The preset as it's stored in project config.
     */
    public function getConfig(): array
    {
        return [
            'name' => $this->name,
            'handle' => $this->handle,
            'description' => $this->description,
            'audienceType' => $this->audienceType,
            'audienceSettings' => $this->audienceSettings,
            'emails' => array_values($this->emails),
            'overrides' => $this->overrides,
            'sortOrder' => $this->sortOrder,
        ];
    }

    public function hasDiscountEmail(int $index): bool
    {
        $email = $this->emails[$index] ?? [];

        return !empty($email['offersDiscount']) || !empty($email['discountCode']);
    }

    public function validateEmails(string $attribute): void
    {
        if (!$this->emails) {
            $this->addError($attribute, Craft::t('abandoned-cart', 'Add at least one email.'));

            return;
        }

        if (count($this->emails) > Email::MAX_PER_SEQUENCE) {
            $this->addError($attribute, Craft::t('abandoned-cart', 'A preset can have up to {max} emails.', ['max' => Email::MAX_PER_SEQUENCE]));
        }

        foreach (array_values($this->emails) as $index => $email) {
            if (trim((string)($email['subject'] ?? '')) === '') {
                $this->addError($attribute, Craft::t('abandoned-cart', 'Email {number} needs a subject.', ['number' => $index + 1]));
            }
        }
    }


    // Protected Methods
    // =========================================================================

    protected function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['handle', 'name'], 'required'];
        $rules[] = [['audienceType'], 'in', 'range' => Sequence::audienceTypes()];
        $rules[] = [['handle'], HandleValidator::class, 'on' => self::SCENARIO_SETTINGS];
        $rules[] = [['name', 'handle'], 'string', 'max' => 255, 'on' => self::SCENARIO_SETTINGS];
        $rules[] = [['emails'], 'validateEmails', 'on' => self::SCENARIO_SETTINGS];

        return $rules;
    }
}
