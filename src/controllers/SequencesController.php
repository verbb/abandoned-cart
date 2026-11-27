<?php
namespace verbb\abandonedcart\controllers;

use verbb\abandonedcart\AbandonedCart;
use verbb\abandonedcart\helpers\Duration;
use verbb\abandonedcart\models\Email;
use verbb\abandonedcart\models\Sequence;
use verbb\abandonedcart\services\State;

use Craft;
use craft\db\Query;
use craft\helpers\DateTimeHelper;

use DateTime;

use yii\web\NotFoundHttpException;
use yii\web\Response;

use craft\commerce\db\Table as CommerceTable;
use craft\commerce\elements\Order;
use craft\commerce\elements\Product;

use verbb\base\helpers\EnvironmentSettings;

class SequencesController extends BaseCpController
{
    // Public Methods
    // =========================================================================

    public function actionIndex(): Response
    {
        $plugin = AbandonedCart::$plugin;
        $sequences = $plugin->getSequences()->getAllSequences();
        $stats = $plugin->getReports()->getSequenceStats(new DateTime('-30 days'));
        $isFresh = count($sequences) === 1 && !$sequences[0]->enabled && !$plugin->getReports()->hasAnyRecoveries();

        return $this->renderTemplate('abandoned-cart/sequences/index', [
            'sequences' => array_map(fn(Sequence $sequence): array => [
                'sequence' => $sequence,
                'audience' => $plugin->getAudiences()->describe($sequence),
                'schedule' => $this->_schedule($sequence),
                'stats' => $stats[$sequence->id] ?? null,
            ], $sequences),
            'presets' => $plugin->getPresets()->getAllPresets(),
            'isFresh' => $isFresh,
        ]);
    }

    public function actionEdit(?int $sequenceId = null, ?Sequence $sequence = null): Response
    {
        $plugin = AbandonedCart::$plugin;

        if (!$sequence) {
            $sequence = $sequenceId ? $plugin->getSequences()->getSequenceById($sequenceId) : null;

            if (!$sequence) {
                throw new NotFoundHttpException('Sequence not found');
            }
        }

        // Opening the default sequence ticks the Overview's "Review your emails" step, which an unchanged sequence can't show otherwise.
        if ($sequence->isFallback && Craft::$app->getUser()->checkPermission(AbandonedCart::PERMISSION_MANAGE_SEQUENCES) && !$plugin->getState()->get(State::EMAILS_REVIEWED_AT)) {
            $plugin->getState()->set(State::EMAILS_REVIEWED_AT, (new DateTime())->format(DATE_ATOM));
        }

        $emails = $sequence->getEmails();
        $stats = $plugin->getReports()->getEmailStats($emails, new DateTime('-30 days'));
        $sequenceStats = $plugin->getReports()->getSequenceStats(new DateTime('-30 days'))[$sequence->id] ?? null;
        $settings = $plugin->getSettings();

        $condition = $plugin->getAudiences()->createOrderCondition($sequence->audienceSettings['condition'] ?? []);

        return $this->renderTemplate('abandoned-cart/sequences/_edit', [
            'sequence' => $sequence,
            'emails' => $emails,
            'emailStats' => $stats,
            'sequenceStats' => $sequenceStats,
            'schedule' => $this->_schedule($sequence),
            'audiences' => $plugin->getAudiences()->getAllAudiences(),
            'audienceDescription' => $plugin->getAudiences()->describe($sequence),
            'conditionHtml' => $condition->getBuilderHtml(),
            'products' => $this->_chosenProducts($sequence),
            'inRecovery' => $plugin->getReports()->countActiveRecoveries($sequence->id),
            'settings' => $settings,
            'emailTemplates' => $plugin->getEmailTemplates()->getAllTemplates(),
            'editorConfig' => [
                'sequenceId' => $sequence->id,
                'isFallback' => $sequence->isFallback,
                'maxEmails' => Email::MAX_PER_SEQUENCE,
                'testEmail' => Craft::$app->getUser()->getIdentity()?->email,
                'tokens' => $plugin->getEmailRenderer()::tokenLabels(),
                'starts' => $this->_starts(),
                'templates' => array_map(static fn($template): array => ['id' => $template->id, 'name' => $template->name, 'isDefault' => $template->isDefault], $plugin->getEmailTemplates()->getAllTemplates()),
                'abandonedAfter' => Duration::humanize($sequence->getAbandonedAfterMinutes()),
                'discountCodes' => $this->_discountCodes(),
            ],
            'isSiteMulti' => Craft::$app->getIsMultiSite(),
        ]);
    }

    public function actionCreate(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission(AbandonedCart::PERMISSION_MANAGE_SEQUENCES);

        $plugin = AbandonedCart::$plugin;
        $handle = (string)$this->request->getBodyParam('preset');
        $notices = [];

        if ($handle && ($preset = $plugin->getPresets()->getPresetByHandle($handle))) {
            $sequence = $plugin->getPresets()->createSequenceFromPreset($preset, [], $notices);
        } else {
            $sequence = new Sequence(['name' => Craft::t('abandoned-cart', 'New Sequence')]);
            $plugin->getSequences()->saveSequence($sequence);
        }

        if (!$sequence->id) {
            return $this->asFailure(Craft::t('abandoned-cart', 'Couldn’t create the sequence: {error}', ['error' => implode(' ', $sequence->getFirstErrors())]));
        }

        foreach ($notices as $notice) {
            Craft::$app->getSession()->setNotice($notice);
        }

        return $this->redirect($sequence->getCpEditUrl());
    }

    public function actionSave(): ?Response
    {
        $this->requirePostRequest();
        $this->requirePermission(AbandonedCart::PERMISSION_MANAGE_SEQUENCES);

        $sequences = AbandonedCart::$plugin->getSequences();
        $sequence = $sequences->getSequenceById((int)$this->request->getRequiredBodyParam('sequenceId'));

        if (!$sequence) {
            throw new NotFoundHttpException('Sequence not found');
        }

        $sequence->name = (string)$this->request->getBodyParam('name', $sequence->name);
        $sequence->enabled = (bool)$this->request->getBodyParam('enabled', $sequence->enabled);

        if (!$sequence->isFallback && $this->request->getBodyParam('audienceType') !== null) {
            $sequence->audienceType = (string)$this->request->getBodyParam('audienceType');
            $sequence->audienceSettings = $this->_audienceSettings($sequence->audienceType, (array)$this->request->getBodyParam('audienceSettings', []));
        }

        // Fields that weren't posted keep their values, so a quick On/Off form doesn't clear anything else.
        if (($siteIds = $this->request->getBodyParam('siteIds')) !== null) {
            $sequence->siteIds = is_array($siteIds) ? array_values(array_map('intval', array_filter($siteIds))) : [];
        }

        if (($override = $this->request->getBodyParam('overrideDefaults')) !== null) {
            // Overrides only apply while their switch is on, so switching it off returns to the Recovery Defaults.
            foreach (['abandonedAfterMinutes', 'recoveryWindowMinutes', 'attributionWindowMinutes', 'contactLimitMinutes'] as $key) {
                $sequence->$key = $override ? Duration::toMinutes($this->request->getBodyParam($key)) : null;
            }

            $returnUrl = $override ? (trim((string)$this->request->getBodyParam('returnUrl')) ?: null) : null;

            // Customers are redirected to the resolved return page, so only admins can point it at an environment
            // variable. A reference an admin already saved can stay.
            $violation = EnvironmentSettings::violation(['returnUrl' => $returnUrl], ['returnUrl' => $sequence->returnUrl], []);
            $sequence->returnUrl = $returnUrl;

            if ($violation) {
                $sequence->addError('returnUrl', Craft::t('abandoned-cart', 'Only admins can use environment variables here.'));

                return $this->asModelFailure($sequence, Craft::t('abandoned-cart', 'Couldn’t save sequence.'), 'sequence');
            }
        }

        if (!$sequences->saveSequence($sequence)) {
            return $this->asModelFailure($sequence, Craft::t('abandoned-cart', 'Couldn’t save sequence.'), 'sequence');
        }

        return $this->asModelSuccess($sequence, Craft::t('abandoned-cart', 'Sequence saved.'), 'sequence');
    }

    public function actionToggle(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $this->requirePermission(AbandonedCart::PERMISSION_MANAGE_SEQUENCES);

        $sequences = AbandonedCart::$plugin->getSequences();
        $sequence = $sequences->getSequenceById((int)$this->request->getRequiredBodyParam('id'));

        if (!$sequence) {
            throw new NotFoundHttpException('Sequence not found');
        }

        $sequence->enabled = (bool)$this->request->getRequiredBodyParam('enabled');

        if (!$sequences->saveSequence($sequence)) {
            return $this->asFailure(implode(' ', $sequence->getFirstErrors()));
        }

        return $this->asSuccess($sequence->enabled
            ? Craft::t('abandoned-cart', '“{name}” is on. It takes carts abandoned from now.', ['name' => $sequence->name])
            : Craft::t('abandoned-cart', '“{name}” is off. Its unsent emails wait until it’s on again.', ['name' => $sequence->name]));
    }

    public function actionReorder(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $this->requirePermission(AbandonedCart::PERMISSION_MANAGE_SEQUENCES);

        AbandonedCart::$plugin->getSequences()->reorderSequences((array)$this->request->getRequiredBodyParam('ids'));

        return $this->asSuccess(Craft::t('abandoned-cart', 'Order saved. It applies to new carts straight away.'));
    }

    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission(AbandonedCart::PERMISSION_MANAGE_SEQUENCES);

        $sequences = AbandonedCart::$plugin->getSequences();
        $sequence = $sequences->getSequenceById((int)$this->request->getRequiredBodyParam('id'));

        if (!$sequence || !$sequences->deleteSequence($sequence)) {
            return $this->asFailure($sequence?->getFirstError('isFallback') ?? Craft::t('abandoned-cart', 'Couldn’t delete sequence.'));
        }

        return $this->asSuccess(Craft::t('abandoned-cart', 'Sequence deleted.'), [], 'abandoned-cart/sequences');
    }

    public function actionTestCart(): Response
    {
        $this->requireAcceptsJson();

        $plugin = AbandonedCart::$plugin;
        $sequence = $plugin->getSequences()->getSequenceById((int)$this->request->getRequiredParam('sequenceId'));
        $order = Order::find()->id((int)$this->request->getRequiredParam('orderId'))->status(null)->one();

        if (!$sequence || !$order) {
            throw new NotFoundHttpException('Sequence or cart not found');
        }

        $matches = $plugin->getAudiences()->matches($sequence, $order);
        $earlier = null;

        // A cart is checked against sequences from the top, so an earlier one can take it first.
        if ($matches) {
            foreach ($plugin->getSequences()->getEnabledSequences() as $candidate) {
                if ($candidate->id === $sequence->id) {
                    break;
                }

                if ($plugin->getAudiences()->matches($candidate, $order)) {
                    $earlier = $candidate;

                    break;
                }
            }
        }

        return $this->asJson([
            'matches' => $matches,
            'message' => match (true) {
                !$matches => Craft::t('abandoned-cart', 'Doesn’t match: {audience}.', ['audience' => mb_strtolower($plugin->getAudiences()->describe($sequence))]),
                $earlier !== null => Craft::t('abandoned-cart', 'Matches, but “{name}” is higher in the list and would take it first.', ['name' => $earlier->name]),
                default => Craft::t('abandoned-cart', 'Matches: {audience}.', ['audience' => mb_strtolower($plugin->getAudiences()->describe($sequence))]),
            },
        ]);
    }

    public function actionSearchCarts(): Response
    {
        $this->requireAcceptsJson();

        $search = trim((string)$this->request->getParam('search', ''));
        $query = Order::find()
            ->isCompleted(false)
            ->hasLineItems(true)
            ->email(':notempty:')
            ->status(null)
            ->orderBy(['elements.dateUpdated' => SORT_DESC])
            ->limit(25);

        if ($search !== '') {
            $query->email('*' . $search . '*');
        }

        $formatter = Craft::$app->getFormatter();

        return $this->asJson([
            'carts' => array_map(static fn(Order $order): array => [
                'id' => $order->id,
                'label' => $order->getEmail() . ' · ' . $formatter->asCurrency($order->getTotal(), $order->currency) . ' · ' . $formatter->asRelativeTime(DateTimeHelper::toDateTime($order->dateUpdated)),
            ], $query->all()),
        ]);
    }


    // Private Methods
    // =========================================================================

    private function _schedule(Sequence $sequence): array
    {
        $total = $sequence->getAbandonedAfterMinutes();

        return array_map(static function(Email $email) use (&$total): array {
            $total += $email->delayMinutes;

            return ['email' => $email, 'short' => Duration::short($total), 'after' => Duration::humanize($email->delayMinutes)];
        }, $sequence->getEmails());
    }

    private function _audienceSettings(string $type, array $posted): array
    {
        return match ($type) {
            Sequence::AUDIENCE_CART_VALUE => [
                'operator' => in_array($posted['operator'] ?? '', ['atLeast', 'lessThan', 'between'], true) ? $posted['operator'] : 'atLeast',
                'amount' => isset($posted['amount']) && $posted['amount'] !== '' ? (float)$posted['amount'] : null,
                'maxAmount' => isset($posted['maxAmount']) && $posted['maxAmount'] !== '' ? (float)$posted['maxAmount'] : null,
            ],
            Sequence::AUDIENCE_RETURNING => ['minOrders' => max(1, (int)($posted['minOrders'] ?? 1))],
            Sequence::AUDIENCE_PRODUCTS => [
                'match' => ($posted['match'] ?? 'any') === 'all' ? 'all' : 'any',
                'productIds' => array_values(array_map('intval', array_filter((array)($posted['productIds'] ?? [])))),
            ],
            Sequence::AUDIENCE_CUSTOM => ['condition' => AbandonedCart::$plugin->getAudiences()->createOrderCondition(is_array($posted['condition'] ?? null) ? $posted['condition'] : [])->getConfig()],
            Sequence::AUDIENCE_ALL, Sequence::AUDIENCE_FIRST_TIME => [],
            // A registered audience keeps whatever settings its own fields post.
            default => $posted,
        };
    }

    /**
     * Commerce coupon codes for the email editor's Discount Code menu, with their discount's name. Codes from enabled
     * discounts come first. A store with many generated codes gets the first 500; others can still be typed.
     */
    private function _discountCodes(): array
    {
        return array_map(static fn(array $row): array => [
            'code' => $row['code'],
            'discount' => $row['name'],
            'enabled' => (bool)$row['enabled'],
        ], (new Query())
            ->select(['c.code', 'd.name', 'd.enabled'])
            ->from(['c' => CommerceTable::COUPONS])
            ->innerJoin(['d' => CommerceTable::DISCOUNTS], '[[d.id]] = [[c.discountId]]')
            ->where(['not', ['c.code' => null]])
            ->orderBy(['d.enabled' => SORT_DESC, 'd.name' => SORT_ASC, 'c.code' => SORT_ASC])
            ->limit(500)
            ->all());
    }

    /**
     * The products a Products audience has chosen, in the order they were chosen. None chosen means none: an empty
     * `id()` would match every product.
     */
    private function _chosenProducts(Sequence $sequence): array
    {
        $ids = array_values(array_filter(array_map('intval', (array)($sequence->audienceSettings['productIds'] ?? []))));

        return $ids ? Product::find()->id($ids)->fixedOrder()->status(null)->all() : [];
    }

    /**
     * The New Email panel's Start From choices: blank, then each preset's emails, grouped by preset.
     */
    private function _starts(): array
    {
        $starts = [
            'blank' => ['label' => Craft::t('abandoned-cart', 'Blank'), 'subject' => '', 'previewText' => '', 'heading' => '', 'message' => '', 'buttonLabel' => Craft::t('abandoned-cart', 'Return to your cart')],
        ];

        foreach (AbandonedCart::$plugin->getPresets()->getAllPresets() as $preset) {
            foreach ($preset->emails as $index => $email) {
                $starts[$preset->handle . ':' . $index] = [
                    'label' => $email['subject'] ?? Craft::t('abandoned-cart', 'Email {number}', ['number' => $index + 1]),
                    'group' => $preset->name,
                    'subject' => $email['subject'] ?? '',
                    'previewText' => $email['previewText'] ?? '',
                    'heading' => $email['heading'] ?? '',
                    'message' => $email['message'] ?? '',
                    'buttonLabel' => $email['buttonLabel'] ?? '',
                    'discountCode' => $email['discountCode'] ?? '',
                ];
            }
        }

        return $starts;
    }
}
