<?php
namespace verbb\abandonedcart\console\controllers;

use verbb\abandonedcart\AbandonedCart;

use Craft;
use craft\helpers\Console;

use yii\console\Controller;
use yii\console\ExitCode;

class RemindersController extends Controller
{
    // Properties
    // =========================================================================

    public $defaultAction = 'scheduleEmails';


    // Public Methods
    // =========================================================================

    /**
     * Finds all abandoned carts and sends reminder
     */
    public function actionScheduleEmails(): int
    {
        $this->stdout('Abandoned Cart: Finding carts' . PHP_EOL, Console::FG_YELLOW);
        
        $cartCount = AbandonedCart::$plugin->getCarts()->getEmailsToSend();
        
        if ($cartCount) {
            $this->stdout('Carts Found: ' . $cartCount . PHP_EOL, Console::FG_GREEN);
        } else {
            $this->stdout('No carts were found' . PHP_EOL, Console::FG_RED);
        }
        
        $this->stdout('Abandoned Cart: Job completed' . PHP_EOL, Console::FG_YELLOW);
        
        return ExitCode::OK;
    }
}
