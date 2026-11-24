<?php
namespace verbb\abandonedcart\controllers;

use verbb\abandonedcart\AbandonedCart;
use verbb\abandonedcart\db\Table;
use verbb\abandonedcart\helpers\Duration;
use verbb\abandonedcart\models\Attempt;
use verbb\abandonedcart\models\Recovery;
use verbb\abandonedcart\services\Presets;
use verbb\abandonedcart\services\State;

use Craft;
use craft\db\Query;
use craft\helpers\App;
use craft\helpers\DateTimeHelper;
use craft\helpers\UrlHelper;

use DateInterval;
use DateTime;
use Throwable;
use yii\web\Response;

class OverviewController extends BaseCpController
{
    // Constants
    // =========================================================================

    public const RANGES = [7, 30, 90];


    // Public Methods
    // =========================================================================

    public function actionIndex(): Response
    {
        $plugin = AbandonedCart::$plugin;
        $reports = $plugin->getReports();
        $sequences = $plugin->getSequences();
        $fallback = $sequences->getFallbackSequence();

        // Setup runs until the first real reminder goes out, so the Overview never shows a dashboard with nothing in it.
        if (!$this->_hasSentFirstEmail()) {
            $state = $plugin->getState();
            $enabled = $sequences->getEnabledSequences();

            return $this->renderTemplate('abandoned-cart/overview/_setup', [
                'fallback' => $fallback,
                'enabled' => $enabled,
                // The first sequence on is the one a new cart most likely meets, so its timing describes what happens next.
                'schedule' => $this->_schedule($enabled[0] ?? $fallback),
                'sending' => $this->_sendingCheck(),
                'testSentAt' => $state->get(State::TEST_SENT_AT),
                'emailsReviewed' => $state->get(State::EMAILS_REVIEWED_AT) || $this->_emailsEdited($fallback),
                'rightNow' => $reports->getRightNow(),
            ]);
        }

        $rightNow = $reports->getRightNow();

        $days = (int)$this->request->getParam('range', 30);
        $days = in_array($days, self::RANGES, true) ? $days : 30;
        $end = new DateTime('tomorrow');
        $start = (clone $end)->sub(new DateInterval("P{$days}D"));
        $previousStart = (clone $start)->sub(new DateInterval("P{$days}D"));

        $current = $reports->getSummary($start, $end);
        $previous = $reports->getSummary($previousStart, $start);
        $currentDays = $reports->getDailyRevenue($start, $end);
        $previousDays = array_values($reports->getDailyRevenue($previousStart, $start));
        $reasons = $reports->getOutcomeReasons($start, $end);
        $allSequences = $sequences->getAllSequences();
        $sequenceStats = $reports->getSequenceStats($start);

        return $this->renderTemplate('abandoned-cart/overview/index', [
            'range' => $days,
            'ranges' => self::RANGES,
            'rightNow' => $rightNow,
            'current' => $current,
            'previous' => $previous,
            'currency' => $reports->getCurrency(),
            'chartData' => [
                'cols' => [
                    ['id' => 'date', 'label' => Craft::t('abandoned-cart', 'Date'), 'type' => 'date', 'bucket' => 'day'],
                    ['id' => 'revenue', 'label' => Craft::t('abandoned-cart', 'Recovered revenue'), 'type' => 'money', 'currency' => $reports->getCurrency()],
                ],
                'currentLabel' => Craft::t('abandoned-cart', 'Last {count} days', ['count' => $days]),
                'comparisonLabel' => Craft::t('abandoned-cart', 'Previous {count} days', ['count' => $days]),
                'rows' => array_map(null, array_keys($currentDays), array_values($currentDays)),
                'comparisonRows' => array_map(static fn(string $date, int $index) => [$date, $previousDays[$index] ?? null], array_keys($currentDays), array_keys(array_keys($currentDays))),
            ],
            'funnelData' => [
                'cols' => [['id' => 'stage', 'label' => Craft::t('abandoned-cart', 'Stage'), 'type' => 'category'], ['id' => 'carts', 'label' => Craft::t('abandoned-cart', 'Carts'), 'type' => 'number']],
                'rows' => [
                    [Craft::t('abandoned-cart', 'Carts Abandoned'), $current['abandoned']],
                    [Craft::t('abandoned-cart', 'Emailed'), $current['emailed']],
                    [Craft::t('abandoned-cart', 'Clicked Through'), $current['clicked']],
                    [Craft::t('abandoned-cart', 'Recovered'), $current['recovered']],
                ],
                'notes' => [
                    Craft::t('abandoned-cart', '{value} left in carts', ['value' => Craft::$app->getFormatter()->asCurrency($current['abandonedValue'], $reports->getCurrency())]),
                    ['rate' => $current['abandoned'] ? $current['emailed'] / $current['abandoned'] : 0, 'text' => Craft::t('abandoned-cart', 'reachable · {count} skipped', ['count' => $current['skipped']])],
                    ['rate' => $current['emailed'] ? $current['clicked'] / $current['emailed'] : 0, 'text' => Craft::t('abandoned-cart', 'of emailed')],
                    ['rate' => $current['emailed'] ? $current['recovered'] / $current['emailed'] : 0, 'text' => Craft::t('abandoned-cart', 'of emailed')],
                ],
            ],
            'reasonData' => $this->_reasonData($reasons),
            'sequences' => array_map(fn($sequence): array => [
                'sequence' => $sequence,
                'stats' => $sequenceStats[$sequence->id] ?? null,
                'emails' => array_map(static fn($step): array => $step, $this->_schedule($sequence)),
                'emailStats' => $reports->getEmailStats($sequence->getEmails(), $start),
            ], $allSequences),
            'recent' => $reports->getRecentRecoveries(),
            'frequent' => $reports->countFrequentAbandoners(new DateTime('-30 days')),
        ]);
    }

    public function actionSendTests(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission(AbandonedCart::PERMISSION_MANAGE_SEQUENCES);

        $to = trim((string)$this->request->getRequiredBodyParam('to'));

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return $this->asFailure(Craft::t('abandoned-cart', 'Enter a valid email address to send the tests to.'));
        }

        $fallback = AbandonedCart::$plugin->getSequences()->getFallbackSequence();
        $sent = 0;

        try {
            foreach ($fallback?->getEmails() ?? [] as $email) {
                $sent += AbandonedCart::$plugin->getAttempts()->sendTest($email, $to) ? 1 : 0;
            }
        } catch (Throwable $e) {
            return $this->asFailure(Craft::t('abandoned-cart', 'The tests couldn’t be sent: {error}', ['error' => $e->getMessage()]));
        }

        return $sent
            ? $this->asSuccess(Craft::t('abandoned-cart', '{count, plural, =1{1 test} other{# tests}} sent to {email}.', ['count' => $sent, 'email' => $to]))
            : $this->asFailure(Craft::t('abandoned-cart', 'The mailer couldn’t send the tests. Check Craft’s email settings.'));
    }


    // Private Methods
    // =========================================================================

    private function _schedule(?\verbb\abandonedcart\models\Sequence $sequence): array
    {
        if (!$sequence) {
            return [];
        }

        $steps = [['label' => Craft::t('abandoned-cart', 'Cart abandoned'), 'detail' => Craft::t('abandoned-cart', '{time} without changes', ['time' => Duration::humanize($sequence->getAbandonedAfterMinutes())])]];

        foreach ($sequence->getEmails() as $email) {
            $steps[] = [
                'label' => Craft::t('abandoned-cart', 'Email {position}', ['position' => $email->position]),
                'detail' => $email->delayMinutes ? Craft::t('abandoned-cart', '{time} later', ['time' => Duration::humanize($email->delayMinutes)]) : Craft::t('abandoned-cart', 'Straight away'),
                'subject' => $email->subject,
            ];
        }

        return $steps;
    }

    /**
     * Whether the default sequence or any of its emails has been changed since the install created them.
     */
    private function _emailsEdited(?\verbb\abandonedcart\models\Sequence $fallback): bool
    {
        if (!$fallback) {
            return false;
        }

        $changed = static fn($model): bool => $model->dateCreated && $model->dateUpdated && $model->dateUpdated->getTimestamp() - $model->dateCreated->getTimestamp() > 60;

        if ($changed($fallback)) {
            return true;
        }

        foreach ($fallback->getEmails() as $email) {
            if ($changed($email)) {
                return true;
            }
        }

        // Adding or removing an email also counts.
        return count($fallback->getEmails()) !== count(AbandonedCart::$plugin->getPresets()->getBuiltInPresetByHandle(Presets::PRESET_THREE_REMINDERS)?->emails ?? []);
    }

    /**
     * Whether a recovery email has ever gone to a customer. Sites that sent before this was recorded, including 4.x
     * history imported on upgrade, are found from their attempts and recorded then.
     */
    private function _hasSentFirstEmail(): bool
    {
        $state = AbandonedCart::$plugin->getState();

        if ($state->get(State::FIRST_SENT_AT)) {
            return true;
        }

        $sent = (new Query())
            ->select(['sentAt' => 'MIN([[sentAt]])', 'count' => 'COUNT(*)'])
            ->from([Table::ATTEMPTS])
            ->where(['status' => Attempt::STATUS_SENT])
            ->one();

        if (empty($sent['count'])) {
            return false;
        }

        $state->set(State::FIRST_SENT_AT, (DateTimeHelper::toDateTime($sent['sentAt']) ?: new DateTime())->format(DATE_ATOM));

        return true;
    }

    /**
     * Who emails come from and who can receive them, or the first thing stopping emails going out.
     */
    private function _sendingCheck(): array
    {
        $settings = AbandonedCart::$plugin->getSettings();
        $mail = App::mailSettings();
        $fromEmail = App::parseEnv($mail->fromEmail);
        $fromName = App::parseEnv($mail->fromName);
        $consentField = $settings->consentFieldHandle ? Craft::$app->getFields()->getFieldByHandle($settings->consentFieldHandle) : null;
        $user = Craft::$app->getUser();
        $isAdmin = $user->getIsAdmin();
        $emailSettings = $isAdmin && Craft::$app->getConfig()->getGeneral()->allowAdminChanges ? ['label' => Craft::t('abandoned-cart', 'Email Settings'), 'url' => UrlHelper::cpUrl('settings/email')] : null;
        $generalSettings = $isAdmin ? ['label' => Craft::t('abandoned-cart', 'General Settings'), 'url' => UrlHelper::cpUrl('abandoned-cart/settings')] : null;

        if (!$fromEmail || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'text' => Craft::t('abandoned-cart', 'Craft’s email settings have no valid sender address, so emails can’t go out.'), 'action' => $emailSettings];
        }

        if ($settings->getDeliveryPaused()) {
            return ['ok' => false, 'text' => Craft::t('abandoned-cart', 'All sending is paused in General Settings, so due emails wait until it’s off.'), 'action' => $generalSettings];
        }

        if ($settings->consentFieldHandle && !$consentField) {
            return ['ok' => false, 'text' => Craft::t('abandoned-cart', 'The consent field “{handle}” doesn’t exist, so nobody is emailed.', ['handle' => $settings->consentFieldHandle]), 'action' => $generalSettings];
        }

        return [
            'ok' => true,
            'text' => $consentField
                ? Craft::t('abandoned-cart', 'Emails come from **{name}** ({email}), only to customers who switch on **{field}**.', ['name' => $fromName, 'email' => $fromEmail, 'field' => $consentField->name])
                : Craft::t('abandoned-cart', 'Emails come from **{name}** ({email}), to every cart with an email address unless you choose a consent field in General Settings.', ['name' => $fromName, 'email' => $fromEmail]),
            'action' => $generalSettings,
        ];
    }

    /**
     * Recoveries that ended without an attributed order, in the groups the Overview shows. Skipped
     * carts are in the funnel instead, and 4.x history doesn't say why it ended.
     */
    private function _reasonData(array $reasons): array
    {
        $ended = $reasons['ended'];
        $sum = static fn(string ...$keys): int => array_sum(array_map(static fn(string $key): int => $ended[$key] ?? 0, $keys));
        $failed = $reasons['failed'] ?? 0;
        $groups = [
            'finished' => [Craft::t('abandoned-cart', 'Sequence finished without a purchase'), max(0, $sum(Recovery::END_FINISHED, Recovery::END_EXPIRED) - $failed)],
            'newerCart' => [Craft::t('abandoned-cart', 'Customer started a newer cart'), $sum(Recovery::END_NEWER_CART)],
            'optedOut' => [Craft::t('abandoned-cart', 'Unsubscribed or on Do Not Email'), $sum(Recovery::END_UNSUBSCRIBED, Recovery::END_DO_NOT_EMAIL)],
            'emptied' => [Craft::t('abandoned-cart', 'Cart emptied or deleted'), $sum(Recovery::END_CART_EMPTIED, Recovery::END_ORDER_REMOVED)],
            'ordered' => [Craft::t('abandoned-cart', 'Ordered, but not from an email'), $sum(Recovery::END_COMPLETED_BEFORE_EMAIL, Recovery::END_COMPLETED_OUTSIDE_WINDOW)],
            'stopped' => [Craft::t('abandoned-cart', 'Stopped by staff'), $sum(Recovery::END_STOPPED)],
            'failed' => [Craft::t('abandoned-cart', 'Email failed to send'), $failed],
        ];
        $rows = [];

        foreach ($groups as $key => [$label, $count]) {
            if ($count > 0) {
                $rows[] = ['key' => $key, 'label' => $label, 'count' => $count];
            }
        }

        usort($rows, static fn(array $a, array $b): int => $b['count'] <=> $a['count']);

        return $rows;
    }
}
