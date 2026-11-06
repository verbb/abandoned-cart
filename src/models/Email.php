<?php
namespace verbb\abandonedcart\models;

use verbb\abandonedcart\AbandonedCart;

use craft\base\Model;

use DateTime;

class Email extends Model
{
    // Constants
    // =========================================================================

    public const MAX_PER_SEQUENCE = 5;


    // Properties
    // =========================================================================

    public ?int $id = null;
    public ?int $sequenceId = null;
    public ?int $position = null;

    // Minutes after the previous email, or after the cart was abandoned for the first.
    public int $delayMinutes = 0;

    public ?string $subject = null;
    public ?string $previewText = null;
    public ?string $heading = null;
    public ?string $message = null;
    public ?string $buttonLabel = null;
    public ?string $discountCode = null;
    public ?int $emailTemplateId = null;
    public ?DateTime $dateCreated = null;
    public ?DateTime $dateUpdated = null;
    public ?string $uid = null;


    // Public Methods
    // =========================================================================

    public function getSequence(): ?Sequence
    {
        return $this->sequenceId ? AbandonedCart::$plugin->getSequences()->getSequenceById($this->sequenceId) : null;
    }

    public function getEmailTemplate(): ?EmailTemplate
    {
        $templates = AbandonedCart::$plugin->getEmailTemplates();

        // A deleted template leaves the email without one, so it falls back to the default.
        return ($this->emailTemplateId ? $templates->getTemplateById($this->emailTemplateId) : null) ?? $templates->getDefaultTemplate();
    }


    // Protected Methods
    // =========================================================================

    protected function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['sequenceId', 'subject'], 'required'];
        $rules[] = [['subject', 'previewText', 'heading', 'buttonLabel', 'discountCode'], 'string', 'max' => 255];
        $rules[] = [['position'], 'integer', 'min' => 1, 'max' => self::MAX_PER_SEQUENCE];
        $rules[] = [['delayMinutes'], 'integer', 'min' => 0];

        return $rules;
    }
}
