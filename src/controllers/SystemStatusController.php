<?php
namespace verbb\abandonedcart\controllers;

use verbb\abandonedcart\AbandonedCart;
use verbb\abandonedcart\db\Table;
use verbb\abandonedcart\models\Attempt;
use verbb\abandonedcart\services\State;

use Craft;
use craft\db\Query;
use craft\helpers\App;
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;
use craft\web\View;

use DateTime;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

class SystemStatusController extends BaseCpController
{
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
        return $this->renderTemplate('abandoned-cart/system-status/index', [
            'checks' => $this->getChecks(),
            'lastRunSummary' => AbandonedCart::$plugin->getState()->get(State::LAST_RUN_SUMMARY),
        ]);
    }

    public function actionRun(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission(AbandonedCart::PERMISSION_MANAGE_SEQUENCES);

        $summary = AbandonedCart::$plugin->getRecoveries()->run();

        if ($summary['locked'] ?? false) {
            return $this->asFailure(Craft::t('abandoned-cart', 'Another run is already in progress.'));
        }

        return $this->asSuccess(Craft::t('abandoned-cart', 'Run finished: {enrolled} enrolled, {sent} sent, {skipped} skipped.', $summary));
    }

    public function getChecks(): array
    {
        $plugin = AbandonedCart::$plugin;
        $settings = $plugin->getSettings();
        $state = $plugin->getState();
        $enabled = $plugin->getSequences()->getEnabledSequences();
        $lastRun = DateTimeHelper::toDateTime($state->get(State::LAST_RUN_AT, '') ?: false);
        $checks = [];

        $checks[] = match (true) {
            !$enabled => ['label' => Craft::t('abandoned-cart', 'Sequences'), 'status' => 'notice', 'message' => Craft::t('abandoned-cart', 'No sequence is on, so no carts are being emailed.')],
            default => ['label' => Craft::t('abandoned-cart', 'Sequences'), 'status' => 'ready', 'message' => Craft::t('abandoned-cart', '{count, plural, =1{1 sequence is on.} other{# sequences are on.}}', ['count' => count($enabled)])],
        };

        $staleAfter = new DateTime('-30 minutes');
        $checks[] = match (true) {
            !$lastRun => ['label' => Craft::t('abandoned-cart', 'Scheduler'), 'status' => $enabled ? 'attention' : 'notice', 'message' => Craft::t('abandoned-cart', 'The scheduler hasn’t run yet. Run `php craft abandoned-cart/recoveries/run` every five minutes from cron, or keep Craft’s queue running.')],
            $lastRun < $staleAfter => ['label' => Craft::t('abandoned-cart', 'Scheduler'), 'status' => $enabled ? 'attention' : 'notice', 'message' => Craft::t('abandoned-cart', 'The scheduler last ran {time}. Check the cron job or Craft’s queue.', ['time' => Craft::$app->getFormatter()->asRelativeTime($lastRun)])],
            default => ['label' => Craft::t('abandoned-cart', 'Scheduler'), 'status' => 'ready', 'message' => Craft::t('abandoned-cart', 'Last ran {time}.', ['time' => Craft::$app->getFormatter()->asRelativeTime($lastRun)])],
        };

        $checks[] = $settings->getDeliveryPaused()
            ? ['label' => Craft::t('abandoned-cart', 'Sending'), 'status' => 'attention', 'message' => Craft::t('abandoned-cart', 'All sending is paused in General Settings. Due emails wait until it’s off.')]
            : ['label' => Craft::t('abandoned-cart', 'Sending'), 'status' => 'ready', 'message' => Craft::t('abandoned-cart', 'Emails go out through Craft’s mailer, from {email}.', ['email' => App::parseEnv(App::mailSettings()->fromEmail)])];

        $failed = (int)(new Query())->from([Table::ATTEMPTS])->where(['status' => Attempt::STATUS_FAILED])->andWhere(['>=', 'dateUpdated', Db::prepareDateForDb(new DateTime('-7 days'))])->count();
        $checks[] = $failed
            ? ['label' => Craft::t('abandoned-cart', 'Recent Failures'), 'status' => 'attention', 'message' => Craft::t('abandoned-cart', '{count, plural, =1{1 email} other{# emails}} couldn’t be sent in the last 7 days. Each cart’s history shows why.', ['count' => $failed])]
            : ['label' => Craft::t('abandoned-cart', 'Recent Failures'), 'status' => 'ready', 'message' => Craft::t('abandoned-cart', 'No emails failed in the last 7 days.')];

        $missing = [];

        foreach ($plugin->getEmailTemplates()->getAllTemplates() as $template) {
            foreach (array_filter([$template->htmlTemplate, $template->textTemplate]) as $path) {
                if (!Craft::$app->getView()->doesTemplateExist($path, View::TEMPLATE_MODE_SITE)) {
                    $missing[] = $path;
                }
            }
        }

        $checks[] = $missing
            ? ['label' => Craft::t('abandoned-cart', 'Email Templates'), 'status' => 'attention', 'message' => Craft::t('abandoned-cart', 'These template files can’t be found: {paths}', ['paths' => implode(', ', $missing)])]
            : ['label' => Craft::t('abandoned-cart', 'Email Templates'), 'status' => 'ready', 'message' => Craft::t('abandoned-cart', 'Every email template can be found.')];

        if ($settings->consentFieldHandle) {
            $field = Craft::$app->getFields()->getFieldByHandle($settings->consentFieldHandle);
            $checks[] = $field
                ? ['label' => Craft::t('abandoned-cart', 'Consent'), 'status' => 'ready', 'message' => Craft::t('abandoned-cart', 'Only customers with {field} switched on are emailed.', ['field' => $field->name])]
                : ['label' => Craft::t('abandoned-cart', 'Consent'), 'status' => 'attention', 'message' => Craft::t('abandoned-cart', 'The consent field “{handle}” doesn’t exist, so nobody is emailed.', ['handle' => $settings->consentFieldHandle])];
        } else {
            $checks[] = ['label' => Craft::t('abandoned-cart', 'Consent'), 'status' => 'notice', 'message' => Craft::t('abandoned-cart', 'Every eligible cart can be emailed. Set a consent field if the law where you trade requires opt-in.')];
        }

        return $checks;
    }
}
