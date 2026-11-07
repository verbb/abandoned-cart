<?php
namespace verbb\abandonedcart\models;

use craft\base\Model;

class Preset extends Model
{
    // Properties
    // =========================================================================

    public ?string $handle = null;
    public ?string $name = null;
    public ?string $description = null;
    public string $audienceType = Sequence::AUDIENCE_ALL;
    public array $audienceSettings = [];

    // Each email is an array of Email attributes, plus an optional `emailTemplate` handle and an optional
    // `offersDiscount` flag that marks the email meant to carry a discount code in the preset picker.
    public array $emails = [];

    // Overrides of the Recovery Defaults, keyed by Sequence attribute.
    public array $overrides = [];


    // Protected Methods
    // =========================================================================

    protected function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['handle', 'name'], 'required'];
        $rules[] = [['audienceType'], 'in', 'range' => Sequence::audienceTypes()];

        return $rules;
    }
}
