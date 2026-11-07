<?php
namespace verbb\abandonedcart\services;

use verbb\abandonedcart\db\Table;

use craft\base\Component;
use craft\db\Query;
use craft\helpers\Db;
use craft\helpers\Json;

/**
 * Small operational values that aren't settings, such as when the scheduler last ran.
 */
class State extends Component
{
    // Constants
    // =========================================================================

    public const LAST_RUN_AT = 'lastRunAt';
    public const LAST_RUN_SUMMARY = 'lastRunSummary';
    public const LAST_QUEUED_AT = 'lastQueuedAt';
    public const TEST_SENT_AT = 'testSentAt';
    public const CLI_PHP_BINARY = 'cliPhpBinary';


    // Public Methods
    // =========================================================================

    public function get(string $name, mixed $default = null): mixed
    {
        $value = (new Query())->select(['value'])->from([Table::STATE])->where(['name' => $name])->scalar();

        return $value === false || $value === null ? $default : Json::decode($value);
    }

    public function set(string $name, mixed $value): void
    {
        Db::upsert(Table::STATE, ['name' => $name, 'value' => Json::encode($value)], ['value' => Json::encode($value)]);
    }
}
