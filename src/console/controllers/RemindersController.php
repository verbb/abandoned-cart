<?php
namespace verbb\abandonedcart\console\controllers;

use verbb\abandonedcart\AbandonedCart;

use Craft;
use craft\console\Controller;
use craft\helpers\Console;

use yii\console\ExitCode;

/**
 * The 4.x console command, kept while compatibility mode is on so existing cron jobs keep working.
 *
 * @deprecated in 5.0.0. Use `abandoned-cart/recoveries/run` instead.
 */
class RemindersController extends Controller
{
    // Public Methods
    // =========================================================================

    /**
     * Runs the 5.0 scheduler. Replace it in cron with `php craft abandoned-cart/recoveries/run`.
     */
    public function actionScheduleEmails(): int
    {
        if (!AbandonedCart::$plugin->getSettings()->compatibilityMode) {
            $this->stderr('abandoned-cart/reminders/schedule-emails was removed in 5.0. Run abandoned-cart/recoveries/run instead.' . PHP_EOL, Console::FG_RED);

            return ExitCode::UNSPECIFIED_ERROR;
        }

        Craft::$app->getDeprecator()->log('abandoned-cart/reminders/schedule-emails', '`php craft abandoned-cart/reminders/schedule-emails` is deprecated. Run `php craft abandoned-cart/recoveries/run` from cron instead.');
        $this->stdout('abandoned-cart/reminders/schedule-emails is deprecated. Run abandoned-cart/recoveries/run instead.' . PHP_EOL, Console::FG_YELLOW);

        return $this->run('recoveries/run');
    }
}
