<?php
namespace verbb\abandonedcart\models;

use verbb\abandonedcart\AbandonedCart;

use Craft;
use craft\base\Model;
use craft\helpers\UrlHelper;
use craft\validators\HandleValidator;

use DateTime;

class EmailTemplate extends Model
{
    // Properties
    // =========================================================================

    public ?int $id = null;
    public ?string $name = null;
    public ?string $handle = null;

    // Site template paths. A blank HTML template uses the bundled design.
    public ?string $htmlTemplate = null;
    public ?string $textTemplate = null;

    public bool $isDefault = false;
    public int $sortOrder = 0;
    public ?DateTime $dateCreated = null;
    public ?DateTime $dateUpdated = null;
    public ?string $uid = null;


    // Public Methods
    // =========================================================================

    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl('abandoned-cart/settings/email-templates/' . $this->id);
    }

    public function getUsesBundledDesign(): bool
    {
        return !$this->htmlTemplate;
    }

    public function getConfig(): array
    {
        return [
            'name' => $this->name,
            'handle' => $this->handle,
            'htmlTemplate' => $this->htmlTemplate ?: null,
            'textTemplate' => $this->textTemplate ?: null,
            'isDefault' => $this->isDefault,
            'sortOrder' => $this->sortOrder,
        ];
    }


    // Protected Methods
    // =========================================================================

    protected function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['name', 'handle'], 'required'];
        $rules[] = [['name', 'handle'], 'string', 'max' => 255];
        $rules[] = [['htmlTemplate', 'textTemplate'], 'string', 'max' => 500];
        $rules[] = [['handle'], HandleValidator::class];
        $rules[] = [['handle'], function(string $attribute): void {
            $existing = AbandonedCart::$plugin->getEmailTemplates()->getTemplateByHandle((string)$this->handle);

            if ($existing && $existing->id !== $this->id) {
                $this->addError($attribute, Craft::t('abandoned-cart', 'This handle is already in use.'));
            }
        }];
        $rules[] = [['isDefault'], function(string $attribute): void {
            $default = AbandonedCart::$plugin->getEmailTemplates()->getDefaultTemplate();

            // Emails without a template need somewhere to go, so the default can only move, never disappear.
            if (!$this->isDefault && $default && $default->id === $this->id) {
                $this->addError($attribute, Craft::t('abandoned-cart', 'Make another template the default first.'));
            }
        }];

        return $rules;
    }
}
