<?php
namespace verbb\abandonedcart\console\controllers;

use verbb\abandonedcart\AbandonedCart;
use verbb\abandonedcart\services\Runs;

use craft\console\Controller;
use craft\helpers\Console;

use yii\console\ExitCode;

/**
 * Runs Abandoned Cart's scheduler.
 */
class RecoveriesController extends Controller
{
    // Public Methods
    // =========================================================================

    /**
     * Ends expired recoveries, enrols carts that have gone idle and sends the emails that are due. Run it every five
     * minutes from cron: `php craft abandoned-cart/recoveries/run`.
     */
    public function actionRun(): int
    {
        $summary = AbandonedCart::$plugin->getRecoveries()->run(null, Runs::TRIGGER_CONSOLE);

        if ($summary['locked'] ?? false) {
            $this->stderr('Another run is already in progress.' . PHP_EOL, Console::FG_YELLOW);

            return ExitCode::OK;
        }

        $this->stdout(sprintf(
            'Enrolled %d, skipped %d, sent %d, failed %d, not sent %d, ended %d.' . PHP_EOL,
            $summary['enrolled'],
            $summary['skipped'],
            $summary['sent'],
            $summary['failed'],
            $summary['cancelled'],
            $summary['ended'],
        ), Console::FG_GREEN);

        return ExitCode::OK;
    }
}
