<?php
namespace verbb\abandonedcart\controllers;

use verbb\abandonedcart\AbandonedCart;
use verbb\abandonedcart\db\Table;
use verbb\abandonedcart\models\EmailTemplate;
use verbb\abandonedcart\services\EmailRenderer;

use Craft;
use craft\db\Query;
use craft\helpers\FileHelper;
use craft\helpers\StringHelper;

use yii\web\NotFoundHttpException;
use yii\web\Response;

class EmailTemplatesController extends BaseAdminController
{
    // Public Methods
    // =========================================================================

    public function actionIndex(): Response
    {
        $usage = (new Query())
            ->select(['emailTemplateId', 'count' => 'COUNT(*)'])
            ->from([Table::EMAILS])
            ->groupBy(['emailTemplateId'])
            ->pairs();
        $default = AbandonedCart::$plugin->getEmailTemplates()->getDefaultTemplate();

        return $this->renderTemplate('abandoned-cart/email-templates/index', [
            'templates' => AbandonedCart::$plugin->getEmailTemplates()->getAllTemplates(),
            // Emails without a template use the default, so they count towards it.
            'usage' => array_map('intval', $usage) + ['' => 0],
            'defaultId' => $default?->id,
        ]);
    }

    public function actionEdit(?int $templateId = null, ?EmailTemplate $template = null): Response
    {
        if (!$template) {
            $template = $templateId ? AbandonedCart::$plugin->getEmailTemplates()->getTemplateById($templateId) : new EmailTemplate();

            if (!$template) {
                throw new NotFoundHttpException('Email template not found');
            }
        }

        return $this->renderTemplate('abandoned-cart/email-templates/_edit', [
            'template' => $template,
            'isNew' => !$template->id,
        ]);
    }

    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $templates = AbandonedCart::$plugin->getEmailTemplates();
        $id = $this->request->getBodyParam('templateId');
        $template = $id ? $templates->getTemplateById((int)$id) : new EmailTemplate();

        if (!$template) {
            throw new NotFoundHttpException('Email template not found');
        }

        $template->name = $this->request->getBodyParam('name');
        $template->handle = $this->request->getBodyParam('handle');
        $template->htmlTemplate = trim((string)$this->request->getBodyParam('htmlTemplate')) ?: null;
        $template->textTemplate = trim((string)$this->request->getBodyParam('textTemplate')) ?: null;
        $template->isDefault = (bool)$this->request->getBodyParam('isDefault', $template->isDefault);
        $copy = !$template->id && $template->htmlTemplate && $this->request->getBodyParam('copyTemplates');

        if (!$templates->saveTemplate($template)) {
            return $this->asModelFailure($template, Craft::t('abandoned-cart', 'Couldn’t save email template.'), 'template');
        }

        if ($copy) {
            $this->_copyBundledTemplates($template);
        }

        return $this->asModelSuccess($template, Craft::t('abandoned-cart', 'Email template saved.'), 'template');
    }

    public function actionMakeDefault(): Response
    {
        $this->requirePostRequest();

        $templates = AbandonedCart::$plugin->getEmailTemplates();
        $template = $templates->getTemplateById((int)$this->request->getRequiredBodyParam('id'));

        if (!$template) {
            throw new NotFoundHttpException('Email template not found');
        }

        $template->isDefault = true;
        $templates->saveTemplate($template);

        return $this->asSuccess(Craft::t('abandoned-cart', '“{name}” is now the default template.', ['name' => $template->name]));
    }

    public function actionDelete(): Response
    {
        $this->requirePostRequest();

        $templates = AbandonedCart::$plugin->getEmailTemplates();
        $template = $templates->getTemplateById((int)$this->request->getRequiredBodyParam('id'));

        if (!$template || !$templates->deleteTemplate($template)) {
            return $this->asFailure($template?->getFirstError('isDefault') ?? Craft::t('abandoned-cart', 'Couldn’t delete email template.'));
        }

        return $this->asSuccess(Craft::t('abandoned-cart', 'Email template deleted.'));
    }


    // Private Methods
    // =========================================================================

    private function _copyBundledTemplates(EmailTemplate $template): void
    {
        $source = Craft::getAlias('@verbb/abandonedcart/templates/_special/email');
        $siteTemplates = Craft::$app->getPath()->getSiteTemplatesPath();
        $targets = [
            'html.twig' => $template->htmlTemplate,
            'text.twig' => $template->textTemplate,
        ];

        foreach ($targets as $file => $path) {
            if (!$path) {
                continue;
            }

            $destination = $siteTemplates . '/' . ltrim(StringHelper::removeRight($path, '.twig'), '/') . '.twig';

            // Never overwrite a template someone has already written.
            if (is_file($destination)) {
                continue;
            }

            FileHelper::createDirectory(dirname($destination));
            copy($source . '/' . $file, $destination);
        }
    }
}
