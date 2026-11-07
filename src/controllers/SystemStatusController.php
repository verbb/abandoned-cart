<?php
namespace verbb\abandonedcart\controllers;

use verbb\abandonedcart\AbandonedCart;
use verbb\abandonedcart\db\Table;
use verbb\abandonedcart\models\Attempt;
use verbb\abandonedcart\services\Runs;
use verbb\abandonedcart\services\State;

use Craft;
use craft\db\Query;
use craft\helpers\App;
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;
use craft\helpers\UrlHelper;
use craft\web\View;

use DateInterval;
use DateTime;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * System Status: whether the scheduler is running, how it's started, and the health checks that most often stop
 * emails going out. It only reads, so it works where admin changes aren't allowed.
 */
class SystemStatusController extends BaseCpController
{
    // Constants
    // =========================================================================

    public const STATUS_READY = 'ready';
    public const STATUS_NOTICE = 'notice';
    public const STATUS_ATTENTION = 'attention';
    public const STATUS_PROBLEM = 'problem';

    // The scheduler should run every five minutes; after this long without a run, something's wrong.
    private const STALE_MINUTES = 30;


    // Public Methods
    // =========================================================================

    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $user = Craft::$app->getUser();

        if (!$user->getIsAdmin() && !$user->checkPermission(AbandonedCart::PERMISSION_VIEW_SYSTEM_STATUS)) {
            throw new ForbiddenHttpException('User is not permitted to view System Status.');
        }

        return true;
    }

    public function actionIndex(): Response
    {
        $now = new DateTime();
        $scheduler = $this->getScheduler($now);
        $checks = $this->getChecks();
        $issues = array_values(array_filter(
            [$scheduler, ...$checks],
            static fn(array $item): bool => in_array($item['status'], [self::STATUS_ATTENTION, self::STATUS_PROBLEM], true),
        ));
        $day = AbandonedCart::$plugin->getRuns()->getDay($now);

        return $this->renderTemplate('abandoned-cart/system-status/index', [
            'scheduler' => $scheduler,
            'checks' => $checks,
            'issues' => $issues,
            'sequencesOn' => (bool)AbandonedCart::$plugin->getSequences()->getEnabledSequences(),
            'day' => $day,
            'maxSent' => max(1, ...array_map(static fn(array $slot): int => $slot['sent'], $day)),
            'cron' => $this->getCronCommand(),
        ]);
    }

    public function actionRun(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission(AbandonedCart::PERMISSION_MANAGE_SEQUENCES);

        $summary = AbandonedCart::$plugin->getRecoveries()->run(null, Runs::TRIGGER_RUN_NOW);

        if ($summary['locked'] ?? false) {
            return $this->asFailure(Craft::t('abandoned-cart', 'Another run is already in progress.'));
        }

        return $this->asSuccess(Craft::t('abandoned-cart', 'Run finished: {enrolled} enrolled, {sent} sent, {skipped} skipped.', $summary));
    }

    /**
     * When the scheduler last ran, how it's being started, and what the last run did.
     */
    public function getScheduler(?DateTime $now = null): array
    {
        $now ??= new DateTime();
        $plugin = AbandonedCart::$plugin;
        $state = $plugin->getState();
        $formatter = Craft::$app->getFormatter();
        $sequencesOn = (bool)$plugin->getSequences()->getEnabledSequences();
        $lastRun = DateTimeHelper::toDateTime($state->get(State::LAST_RUN_AT, '') ?: false) ?: null;
        $mode = $plugin->getRuns()->getSchedulerMode($now);
        $stale = $lastRun && $lastRun < (clone $now)->sub(new DateInterval('PT' . self::STALE_MINUTES . 'M'));
        $quiet = $sequencesOn ? self::STATUS_ATTENTION : self::STATUS_NOTICE;

        $scheduler = [
            'label' => Craft::t('abandoned-cart', 'Scheduler'),
            'lastRun' => $lastRun,
            'summary' => $state->get(State::LAST_RUN_SUMMARY),
            'mode' => $mode,
            'nextRun' => null,
        ];

        if (!$lastRun) {
            return $scheduler + [
                'status' => $quiet,
                'headline' => Craft::t('abandoned-cart', 'The scheduler hasn’t run yet'),
                'message' => Craft::t('abandoned-cart', 'Add the cron job below so Abandoned Cart runs every five minutes.'),
                'issue' => Craft::t('abandoned-cart', 'The scheduler hasn’t run yet, so no emails are going out.'),
            ];
        }

        $headline = Craft::t('abandoned-cart', 'Last ran {time}', ['time' => $formatter->asRelativeTime($lastRun)]);

        if ($stale) {
            return $scheduler + [
                'status' => $quiet,
                'headline' => $headline,
                'message' => Craft::t('abandoned-cart', 'It should run every five minutes. Check the cron job below, or that Craft’s queue is running.'),
                'issue' => Craft::t('abandoned-cart', 'The scheduler hasn’t run since {time}, so no emails are going out.', ['time' => $formatter->asRelativeTime($lastRun)]),
            ];
        }

        $next = (clone $lastRun)->add(new DateInterval('PT5M'));

        return array_merge($scheduler, [
            'status' => self::STATUS_READY,
            'headline' => $headline,
            'nextRun' => in_array($mode, [Runs::MODE_CRON, Runs::MODE_QUEUE], true) ? $next : null,
            'message' => match ($mode) {
                Runs::MODE_CRON => Craft::t('abandoned-cart', 'Every five minutes. The next run is expected around {time}.', ['time' => $formatter->asTime($next, 'short')]),
                Runs::MODE_QUEUE => Craft::t('abandoned-cart', 'Started by Craft’s queue when someone visits the site. Add the cron job below so emails go out on time, even when the site is quiet.'),
                Runs::MODE_CONSOLE => Craft::t('abandoned-cart', 'Started from the command line.'),
                Runs::MODE_LEGACY_URL => Craft::t('abandoned-cart', 'Started by the 4.x scheduler URL, which is deprecated. Switch to the cron job below.'),
                default => Craft::t('abandoned-cart', 'Last started by Run Now.'),
            },
        ]);
    }

    /**
     * The checks that most often stop emails going out, with anything that needs attention first.
     */
    public function getChecks(): array
    {
        $plugin = AbandonedCart::$plugin;
        $settings = $plugin->getSettings();
        $user = Craft::$app->getUser();
        $canChangeSettings = $user->getIsAdmin();
        $canManageSequences = $user->checkPermission(AbandonedCart::PERMISSION_MANAGE_SEQUENCES) || $user->getIsAdmin();
        $checks = [];

        // Sequences
        $enabled = $plugin->getSequences()->getEnabledSequences();
        $names = array_map(static fn($sequence): string => $sequence->name, $enabled);
        $checks[] = [
            'label' => Craft::t('abandoned-cart', 'Sequences'),
            'status' => $enabled ? self::STATUS_READY : self::STATUS_NOTICE,
            'message' => $enabled
                ? Craft::t('abandoned-cart', '{count, plural, =1{1 sequence is on} other{# sequences are on}}: {names}.', ['count' => count($enabled), 'names' => $this->_list($names)])
                : Craft::t('abandoned-cart', 'No sequence is on, so no carts are being emailed.'),
            'action' => $canManageSequences ? ['label' => Craft::t('abandoned-cart', 'Manage sequences'), 'url' => UrlHelper::cpUrl('abandoned-cart/sequences')] : null,
        ];

        // Sending
        $mail = App::mailSettings();
        $paused = $settings->getDeliveryPaused();
        $checks[] = [
            'label' => Craft::t('abandoned-cart', 'Sending'),
            'status' => $paused ? self::STATUS_ATTENTION : self::STATUS_READY,
            'message' => $paused
                ? Craft::t('abandoned-cart', 'All sending is paused in General Settings. Due emails wait until it’s off.')
                : Craft::t('abandoned-cart', 'Emails go out through Craft’s mailer as **{name}** ({email}).', ['name' => App::parseEnv($mail->fromName), 'email' => App::parseEnv($mail->fromEmail)]),
            'issue' => Craft::t('abandoned-cart', 'All sending is paused.'),
            'action' => $paused
                ? ($canChangeSettings ? ['label' => Craft::t('abandoned-cart', 'General Settings'), 'url' => UrlHelper::cpUrl('abandoned-cart/settings')] : null)
                : ($canChangeSettings && Craft::$app->getConfig()->getGeneral()->allowAdminChanges ? ['label' => Craft::t('abandoned-cart', 'Craft email settings'), 'url' => UrlHelper::cpUrl('settings/email')] : null),
        ];

        // Recent failures
        $since = Db::prepareDateForDb(new DateTime('-7 days'));
        $failed = (int)(new Query())->from([Table::ATTEMPTS])->where(['status' => Attempt::STATUS_FAILED])->andWhere(['>=', 'dateUpdated', $since])->count();
        $latest = $failed ? (new Query())
            ->select(['recoveryId', 'failureReason'])
            ->from([Table::ATTEMPTS])
            ->where(['status' => Attempt::STATUS_FAILED])
            ->andWhere(['>=', 'dateUpdated', $since])
            ->orderBy(['dateUpdated' => SORT_DESC, 'id' => SORT_DESC])
            ->one() : null;
        $checks[] = [
            'label' => Craft::t('abandoned-cart', 'Recent Failures'),
            'status' => $failed ? self::STATUS_PROBLEM : self::STATUS_READY,
            'message' => $failed
                ? Craft::t('abandoned-cart', '{count, plural, =1{1 email} other{# emails}} couldn’t be sent in the last 7 days.', ['count' => $failed]) . ($latest['failureReason'] ?? null ? ' ' . Craft::t('abandoned-cart', 'The latest: “{reason}”', ['reason' => $latest['failureReason']]) : '')
                : Craft::t('abandoned-cart', 'No emails failed in the last 7 days.'),
            'issue' => Craft::t('abandoned-cart', '{count, plural, =1{1 email} other{# emails}} failed this week.', ['count' => $failed]),
            'action' => $latest ? ['label' => Craft::t('abandoned-cart', 'Open the latest'), 'url' => UrlHelper::cpUrl('abandoned-cart/carts/' . $latest['recoveryId'])] : null,
        ];

        // Email templates
        $templates = $plugin->getEmailTemplates()->getAllTemplates();
        $missing = [];

        foreach ($templates as $template) {
            foreach (array_filter([$template->htmlTemplate, $template->textTemplate]) as $path) {
                if (!Craft::$app->getView()->doesTemplateExist($path, View::TEMPLATE_MODE_SITE)) {
                    $missing[] = $path;
                }
            }
        }

        $checks[] = [
            'label' => Craft::t('abandoned-cart', 'Email Templates'),
            'status' => $missing ? self::STATUS_PROBLEM : self::STATUS_READY,
            'message' => $missing
                ? Craft::t('abandoned-cart', 'These template files can’t be found: {paths}', ['paths' => implode(', ', $missing)])
                : Craft::t('abandoned-cart', '{count, plural, =1{The email template can be found.} other{All # email templates can be found.}}', ['count' => count($templates)]),
            'issue' => Craft::t('abandoned-cart', 'An email template file is missing.'),
            'action' => $canChangeSettings ? ['label' => Craft::t('abandoned-cart', 'Email Templates'), 'url' => UrlHelper::cpUrl('abandoned-cart/settings/email-templates')] : null,
        ];

        // Consent
        $field = $settings->consentFieldHandle ? Craft::$app->getFields()->getFieldByHandle($settings->consentFieldHandle) : null;
        $checks[] = [
            'label' => Craft::t('abandoned-cart', 'Consent'),
            'status' => match (true) {
                !$settings->consentFieldHandle => self::STATUS_NOTICE,
                !$field => self::STATUS_PROBLEM,
                default => self::STATUS_READY,
            },
            'message' => match (true) {
                !$settings->consentFieldHandle => Craft::t('abandoned-cart', 'Every eligible cart can be emailed. Set a consent field if the law where you trade requires opt-in.'),
                !$field => Craft::t('abandoned-cart', 'The consent field “{handle}” doesn’t exist, so nobody is emailed.', ['handle' => $settings->consentFieldHandle]),
                default => Craft::t('abandoned-cart', 'Only customers with {field} switched on are emailed.', ['field' => $field->name]),
            },
            'issue' => Craft::t('abandoned-cart', 'The consent field is missing, so nobody is emailed.'),
            'action' => $canChangeSettings ? ['label' => Craft::t('abandoned-cart', 'General Settings'), 'url' => UrlHelper::cpUrl('abandoned-cart/settings')] : null,
        ];

        // Problems first, then anything needing attention; otherwise keep the order above.
        $rank = [self::STATUS_PROBLEM => 0, self::STATUS_ATTENTION => 1, self::STATUS_READY => 2, self::STATUS_NOTICE => 2];
        $order = array_keys($checks);
        usort($order, static fn(int $a, int $b): int => [$rank[$checks[$a]['status']], $a] <=> [$rank[$checks[$b]['status']], $b]);

        return array_map(static fn(int $index): array => $checks[$index], $order);
    }

    /**
     * The crontab line for this server. The command line's PHP is recorded by the last console run; a web request
     * only knows its own PHP, which under PHP-FPM isn't the one cron needs.
     */
    public function getCronCommand(): array
    {
        $php = AbandonedCart::$plugin->getState()->get(State::CLI_PHP_BINARY);

        return [
            'line' => sprintf('*/5 * * * * %s %s abandoned-cart/recoveries/run', $php ?: 'php', Craft::getAlias('@root') . '/craft'),
            'phpKnown' => (bool)$php,
        ];
    }


    // Private Methods
    // =========================================================================

    private function _list(array $names): string
    {
        if (count($names) < 2) {
            return implode('', $names);
        }

        $last = array_pop($names);

        return Craft::t('abandoned-cart', '{list} and {last}', ['list' => implode(', ', $names), 'last' => $last]);
    }
}
