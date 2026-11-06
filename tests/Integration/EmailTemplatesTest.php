<?php

use verbb\abandonedcart\AbandonedCart;
use verbb\abandonedcart\models\Email;
use verbb\abandonedcart\models\EmailTemplate;

// Project config keeps its own copy of what it has applied, so these tests clean up through the service instead of a
// rolled-back transaction, which would leave the two out of step.

function restoreDefaultTemplate(): void
{
    $templates = AbandonedCart::$plugin->getEmailTemplates();
    $default = $templates->getTemplateByHandle('default');
    $default->isDefault = true;
    $templates->saveTemplate($default);

    foreach ($templates->getAllTemplates() as $template) {
        if ($template->handle !== 'default') {
            $templates->deleteTemplate($template);
        }
    }
}

it('saves a template to project config and its table', function() {
    $templates = AbandonedCart::$plugin->getEmailTemplates();
    $template = new EmailTemplate(['name' => 'Editorial', 'handle' => 'editorial', 'htmlTemplate' => '_emails/abandoned-cart/editorial']);

    try {
        expect($templates->saveTemplate($template))->toBeTrue()
            ->and($template->id)->not->toBeNull()
            ->and($templates->getTemplateByHandle('editorial')?->htmlTemplate)->toBe('_emails/abandoned-cart/editorial')
            ->and(Craft::$app->getProjectConfig()->get('abandoned-cart.emailTemplates.' . $template->uid . '.handle'))->toBe('editorial')
            ->and($templates->saveTemplate(new EmailTemplate(['name' => 'Copy', 'handle' => 'editorial'])))->toBeFalse();
    } finally {
        restoreDefaultTemplate();
    }
});

it('keeps exactly one default template', function() {
    $templates = AbandonedCart::$plugin->getEmailTemplates();
    $editorial = new EmailTemplate(['name' => 'Editorial', 'handle' => 'editorial', 'isDefault' => true]);

    try {
        $templates->saveTemplate($editorial);
        $original = $templates->getTemplateByHandle('default');

        expect($templates->getDefaultTemplate()?->handle)->toBe('editorial')
            ->and($original->isDefault)->toBeFalse();

        // The default can only move to another template, never be switched off or deleted.
        $editorial = $templates->getTemplateByHandle('editorial');
        $editorial->isDefault = false;

        expect($templates->saveTemplate($editorial))->toBeFalse()
            ->and($templates->deleteTemplate($templates->getTemplateByHandle('editorial')))->toBeFalse()
            ->and(array_filter($templates->getAllTemplates(), fn(EmailTemplate $template) => $template->isDefault))->toHaveCount(1);
    } finally {
        restoreDefaultTemplate();
    }
});

it('falls back to the default template when an email’s template is deleted', function() {
    $templates = AbandonedCart::$plugin->getEmailTemplates();
    $editorial = new EmailTemplate(['name' => 'Editorial', 'handle' => 'editorial']);
    $email = null;

    try {
        $templates->saveTemplate($editorial);
        $email = AbandonedCart::$plugin->getSequences()->getFallbackSequence()->getEmails()[0];
        $email->emailTemplateId = $editorial->id;
        AbandonedCart::$plugin->getEmails()->saveEmail($email);

        expect(AbandonedCart::$plugin->getEmails()->getEmailById($email->id)->getEmailTemplate()?->handle)->toBe('editorial')
            ->and($templates->deleteTemplate($templates->getTemplateByHandle('editorial')))->toBeTrue();

        $reloaded = AbandonedCart::$plugin->getEmails()->getEmailById($email->id);

        expect($reloaded->emailTemplateId)->toBeNull()
            ->and($reloaded->getEmailTemplate()?->handle)->toBe('default');
    } finally {
        if ($email) {
            $email->emailTemplateId = null;
            AbandonedCart::$plugin->getEmails()->saveEmail($email);
        }

        restoreDefaultTemplate();
    }
});
