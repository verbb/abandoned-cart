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

    /**
     * @var bool Whether CLI output should be muted.
     */
    public bool $silent = false;


    // Public Methods
    // =========================================================================

    public function options($actionID): array
    {
        $options = parent::options($actionID);
        $options[] = 'silent';
        
        return $options;
    }

    /**
     * Finds all abandoned carts and sends reminder
     */
    public function actionScheduleEmails(): int
    {
        $this->_stdout('Abandoned Cart: Finding carts' . PHP_EOL, Console::FG_YELLOW);
        
        $cartCount = AbandonedCart::$plugin->getCarts()->getEmailsToSend();
        
        if ($cartCount) {
            $this->_stdout('Carts Found: ' . $cartCount . PHP_EOL, Console::FG_GREEN);
        } else {
            $this->_stdout('No carts were found' . PHP_EOL, Console::FG_RED);
        }
        
        $this->_stdout('Abandoned Cart: Job completed' . PHP_EOL, Console::FG_YELLOW);
        
        return ExitCode::OK;
    }

    
    // Private Methods
    // =========================================================================

    private function _stdout(string $string, ...$format): void
    {
        if (!$this->silent) {
            $this->stdout($string, ...$format);
        }
    }
}
