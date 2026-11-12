<?php
namespace verbb\abandonedcart\controllers;

use verbb\abandonedcart\AbandonedCart;
use verbb\abandonedcart\models\Email;
use verbb\abandonedcart\models\Preset;
use verbb\abandonedcart\models\Sequence;

use Craft;
use craft\helpers\Json;

use yii\web\NotFoundHttpException;
use yii\web\Response;

class PresetsController extends BaseAdminController
{
    // Constants
    // =========================================================================

    private const UNIT_MINUTES = ['minutes' => 1, 'hours' => 60, 'days' => 1440];


    // Public Methods
    // =========================================================================

    public function actionIndex(): Response
    {
        $presets = AbandonedCart::$plugin->getPresets();

        return $this->renderTemplate('abandoned-cart/presets/index', [
            'presets' => $presets->getSettingsPresets(),
            'pluginPresets' => $presets->getPluginPresets(),
            'audienceLabels' => $this->_audienceLabels(),
        ]);
    }

    public function actionEdit(?string $uid = null, ?Preset $preset = null): Response
    {
        if (!$preset) {
            $preset = $uid ? AbandonedCart::$plugin->getPresets()->getSettingsPresetByUid($uid) : new Preset([
                'emails' => [['delayMinutes' => 60, 'buttonLabel' => Craft::t('abandoned-cart', 'Return to your cart')]],
            ]);

            if (!$preset) {
                throw new NotFoundHttpException('Preset not found');
            }
        }

        return $this->renderTemplate('abandoned-cart/presets/_edit', [
            'preset' => $preset,
            'isNew' => !$preset->uid,
            'audienceOptions' => array_map(static fn(string $value, string $label): array => ['value' => $value, 'label' => $label], array_keys($this->_audienceLabels()), $this->_audienceLabels()),
            'templateOptions' => array_merge(
                [['value' => '', 'label' => Craft::t('abandoned-cart', 'Default template')]],
                array_map(static fn($template): array => ['value' => $template->handle, 'label' => $template->name], AbandonedCart::$plugin->getEmailTemplates()->getAllTemplates()),
            ),
            'maxEmails' => Email::MAX_PER_SEQUENCE,
            'unitMinutes' => self::UNIT_MINUTES,
        ]);
    }

    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $presets = AbandonedCart::$plugin->getPresets();
        $uid = $this->request->getBodyParam('uid');
        $preset = $uid ? $presets->getSettingsPresetByUid($uid) : new Preset();

        if (!$preset) {
            throw new NotFoundHttpException('Preset not found');
        }

        $preset->name = trim((string)$this->request->getBodyParam('name'));
        $preset->handle = trim((string)$this->request->getBodyParam('handle'));
        $preset->description = trim((string)$this->request->getBodyParam('description')) ?: null;
        $preset->audienceType = (string)$this->request->getBodyParam('audienceType', Sequence::AUDIENCE_ALL);
        $preset->audienceSettings = $this->_audienceSettings($preset, (array)$this->request->getBodyParam('audienceSettings', []));
        $preset->emails = $this->_emails((array)$this->request->getBodyParam('emails', []));

        if (!$presets->savePreset($preset)) {
            return $this->asModelFailure($preset, Craft::t('abandoned-cart', 'Couldn’t save preset.'), 'preset');
        }

        return $this->asModelSuccess($preset, Craft::t('abandoned-cart', 'Preset saved.'), 'preset');
    }

    public function actionDelete(): Response
    {
        $this->requirePostRequest();

        $presets = AbandonedCart::$plugin->getPresets();
        $preset = $presets->getSettingsPresetByUid((string)$this->request->getRequiredBodyParam('id'));

        if (!$preset || !$presets->deletePreset($preset)) {
            return $this->asFailure(Craft::t('abandoned-cart', 'Couldn’t delete preset.'));
        }

        return $this->asSuccess(Craft::t('abandoned-cart', 'Preset deleted.'));
    }

    public function actionReorder(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        AbandonedCart::$plugin->getPresets()->reorderPresets(Json::decodeIfJson((string)$this->request->getRequiredBodyParam('ids')) ?: []);

        return $this->asSuccess();
    }


    // Private Methods
    // =========================================================================

    private function _audienceLabels(): array
    {
        return [
            Sequence::AUDIENCE_ALL => Craft::t('abandoned-cart', 'All carts'),
            Sequence::AUDIENCE_CART_VALUE => Craft::t('abandoned-cart', 'Cart value'),
            Sequence::AUDIENCE_RETURNING => Craft::t('abandoned-cart', 'Returning customers'),
            Sequence::AUDIENCE_FIRST_TIME => Craft::t('abandoned-cart', 'First-time customers'),
            Sequence::AUDIENCE_PRODUCTS => Craft::t('abandoned-cart', 'Products'),
            Sequence::AUDIENCE_CUSTOM => Craft::t('abandoned-cart', 'Custom rules'),
        ] + array_map(static fn(array $audience): string => $audience['label'], AbandonedCart::$plugin->getAudiences()->getAllAudiences());
    }

    /**
     * Only a cart value audience has settings a preset can hold; products and custom rules are chosen once the
     * sequence exists. Anything else already stored, such as from a plugin, is kept.
     */
    private function _audienceSettings(Preset $preset, array $posted): array
    {
        if ($preset->audienceType !== Sequence::AUDIENCE_CART_VALUE) {
            return $preset->audienceSettings;
        }

        $amount = trim((string)($posted['amount'] ?? ''));
        $maxAmount = trim((string)($posted['maxAmount'] ?? ''));
        $operator = in_array($posted['operator'] ?? '', ['atLeast', 'lessThan', 'between'], true) ? $posted['operator'] : 'atLeast';

        return array_merge($preset->audienceSettings, [
            'operator' => $operator,
            'amount' => $amount === '' ? null : (float)$amount,
            'maxAmount' => $operator === 'between' && $maxAmount !== '' ? (float)$maxAmount : null,
        ]);
    }

    private function _emails(array $posted): array
    {
        $emails = [];

        foreach (array_values($posted) as $email) {
            $value = max(0, (int)($email['delayValue'] ?? 0));
            $unit = self::UNIT_MINUTES[$email['delayUnit'] ?? 'hours'] ?? 60;
            $row = ['delayMinutes' => $value * $unit];

            foreach (['subject', 'previewText', 'heading', 'message', 'buttonLabel', 'discountCode', 'emailTemplate'] as $field) {
                $text = trim((string)($email[$field] ?? ''));

                if ($text !== '') {
                    $row[$field] = $text;
                }
            }

            if (!empty($email['offersDiscount'])) {
                $row['offersDiscount'] = true;
            }

            $emails[] = $row;
        }

        return $emails;
    }
}
