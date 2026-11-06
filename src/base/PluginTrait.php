<?php
namespace verbb\abandonedcart\base;

use verbb\abandonedcart\AbandonedCart;
use verbb\abandonedcart\services\Customers;
use verbb\abandonedcart\services\Emails;
use verbb\abandonedcart\services\EmailTemplates;
use verbb\abandonedcart\services\Presets;
use verbb\abandonedcart\services\Sequences;

use verbb\base\LogTrait;
use verbb\base\helpers\Plugin;

trait PluginTrait
{
    // Static Properties
    // =========================================================================

    public static ?AbandonedCart $plugin = null;


    // Traits
    // =========================================================================

    use LogTrait;


    // Static Methods
    // =========================================================================

    public static function config(): array
    {
        Plugin::bootstrapPlugin('abandoned-cart');

        return [
            'components' => [
                'customers' => Customers::class,
                'emails' => Emails::class,
                'emailTemplates' => EmailTemplates::class,
                'presets' => Presets::class,
                'sequences' => Sequences::class,
            ],
        ];
    }


    // Public Methods
    // =========================================================================

    public function getCustomers(): Customers
    {
        return $this->get('customers');
    }

    public function getEmails(): Emails
    {
        return $this->get('emails');
    }

    public function getEmailTemplates(): EmailTemplates
    {
        return $this->get('emailTemplates');
    }

    public function getPresets(): Presets
    {
        return $this->get('presets');
    }

    public function getSequences(): Sequences
    {
        return $this->get('sequences');
    }
}
