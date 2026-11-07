<?php
namespace verbb\abandonedcart\base;

use verbb\abandonedcart\AbandonedCart;
use verbb\abandonedcart\services\Activities;
use verbb\abandonedcart\services\Attempts;
use verbb\abandonedcart\services\Audiences;
use verbb\abandonedcart\services\Customers;
use verbb\abandonedcart\services\EmailRenderer;
use verbb\abandonedcart\services\EmailTemplates;
use verbb\abandonedcart\services\Emails;
use verbb\abandonedcart\services\Presets;
use verbb\abandonedcart\services\Recoveries;
use verbb\abandonedcart\services\Reports;
use verbb\abandonedcart\services\Runs;
use verbb\abandonedcart\services\Sequences;
use verbb\abandonedcart\services\State;
use verbb\abandonedcart\services\Templates;

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
                'activities' => Activities::class,
                'attempts' => Attempts::class,
                'audiences' => Audiences::class,
                'customers' => Customers::class,
                'emailRenderer' => EmailRenderer::class,
                'emails' => Emails::class,
                'emailTemplates' => EmailTemplates::class,
                'presets' => Presets::class,
                'recoveries' => Recoveries::class,
                'reports' => Reports::class,
                'runs' => Runs::class,
                'sequences' => Sequences::class,
                'state' => State::class,
                'templates' => Templates::class,
            ],
        ];
    }


    // Public Methods
    // =========================================================================

    public function getActivities(): Activities
    {
        return $this->get('activities');
    }

    public function getAttempts(): Attempts
    {
        return $this->get('attempts');
    }

    public function getAudiences(): Audiences
    {
        return $this->get('audiences');
    }

    public function getCustomers(): Customers
    {
        return $this->get('customers');
    }

    public function getEmailRenderer(): EmailRenderer
    {
        return $this->get('emailRenderer');
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

    public function getRecoveries(): Recoveries
    {
        return $this->get('recoveries');
    }

    public function getReports(): Reports
    {
        return $this->get('reports');
    }

    public function getRuns(): Runs
    {
        return $this->get('runs');
    }

    public function getSequences(): Sequences
    {
        return $this->get('sequences');
    }

    public function getState(): State
    {
        return $this->get('state');
    }

    public function getTemplates(): Templates
    {
        return $this->get('templates');
    }
}
