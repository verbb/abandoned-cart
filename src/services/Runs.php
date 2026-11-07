<?php
namespace verbb\abandonedcart\services;

use verbb\abandonedcart\db\Table;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;
use craft\helpers\StringHelper;

use DateInterval;
use DateTime;
use Throwable;

/**
 * A record of each scheduler run, so System Status can say how it's being started and how it's been going.
 */
class Runs extends Component
{
    // Constants
    // =========================================================================

    // What started a run.
    public const TRIGGER_CONSOLE = 'console';
    public const TRIGGER_QUEUE = 'queue';
    public const TRIGGER_RUN_NOW = 'runNow';
    public const TRIGGER_LEGACY_URL = 'legacyUrl';
    public const TRIGGER_OTHER = 'other';

    // How scheduled runs are being started, judged from recent runs.
    public const MODE_CRON = 'cron';
    public const MODE_CONSOLE = 'console';
    public const MODE_QUEUE = 'queue';
    public const MODE_LEGACY_URL = 'legacyUrl';

    public const KEEP_DAYS = 7;

    // Scheduled runs within this many minutes of each other count as regular.
    private const REGULAR_GAP_MINUTES = 15;


    // Public Methods
    // =========================================================================

    public function record(string $trigger, DateTime $startedAt, int $durationMs, array $summary): void
    {
        try {
            Db::insert(Table::RUNS, [
                'trigger' => $trigger,
                'startedAt' => Db::prepareDateForDb($startedAt),
                'durationMs' => $durationMs,
                'enrolled' => (int)($summary['enrolled'] ?? 0),
                'skipped' => (int)($summary['skipped'] ?? 0),
                'sent' => (int)($summary['sent'] ?? 0),
                'failed' => (int)($summary['failed'] ?? 0),
                'notSent' => (int)($summary['cancelled'] ?? 0),
                'ended' => (int)($summary['ended'] ?? 0),
                'uid' => StringHelper::UUID(),
            ]);
        } catch (Throwable $e) {
            // A run that did its work shouldn't fail because its record couldn't be written.
            Craft::warning('Abandoned Cart couldn’t record a scheduler run: ' . $e->getMessage(), __METHOD__);
        }
    }

    /**
     * Runs since a time, newest first.
     */
    public function getRunsSince(DateTime $since): array
    {
        return (new Query())
            ->select(['trigger', 'startedAt', 'durationMs', 'enrolled', 'skipped', 'sent', 'failed', 'notSent', 'ended'])
            ->from([Table::RUNS])
            ->where(['>=', 'startedAt', Db::prepareDateForDb($since)])
            ->orderBy(['startedAt' => SORT_DESC, 'id' => SORT_DESC])
            ->all();
    }

    /**
     * How scheduled runs are being started, from the last few that weren't Run Now. A console run on its own could
     * be someone typing the command, so it only counts as cron when recent console runs are regular.
     */
    public function getSchedulerMode(?DateTime $now = null): ?string
    {
        $now ??= new DateTime();
        $runs = array_values(array_filter(
            $this->getRunsSince((clone $now)->sub(new DateInterval('PT2H'))),
            static fn(array $run): bool => $run['trigger'] !== self::TRIGGER_RUN_NOW,
        ));

        if (!$runs) {
            return null;
        }

        return match ($runs[0]['trigger']) {
            self::TRIGGER_QUEUE => self::MODE_QUEUE,
            self::TRIGGER_LEGACY_URL => self::MODE_LEGACY_URL,
            self::TRIGGER_CONSOLE => $this->_isRegular(array_slice($runs, 0, 4), self::TRIGGER_CONSOLE) ? self::MODE_CRON : self::MODE_CONSOLE,
            default => null,
        };
    }

    /**
     * The last 24 hours in half-hour slots, oldest first: whether a run happened, and what the runs sent and failed.
     *
     * @return array<int, array{start: DateTime, runs: int, sent: int, failed: int}>
     */
    public function getDay(?DateTime $now = null): array
    {
        $now ??= new DateTime();
        $slotSeconds = 1800;
        $end = (int)(ceil($now->getTimestamp() / $slotSeconds) * $slotSeconds);
        $start = $end - 48 * $slotSeconds;
        $slots = [];

        for ($i = 0; $i < 48; $i++) {
            $slots[$i] = ['start' => (new DateTime())->setTimestamp($start + $i * $slotSeconds), 'runs' => 0, 'sent' => 0, 'failed' => 0];
        }

        foreach ($this->getRunsSince((new DateTime())->setTimestamp($start)) as $run) {
            $index = (int)floor((DateTimeHelper::toDateTime($run['startedAt'])->getTimestamp() - $start) / $slotSeconds);

            if (isset($slots[$index])) {
                $slots[$index]['runs']++;
                $slots[$index]['sent'] += (int)$run['sent'];
                $slots[$index]['failed'] += (int)$run['failed'];
            }
        }

        return $slots;
    }

    public function purge(?DateTime $now = null): void
    {
        $now ??= new DateTime();

        Db::delete(Table::RUNS, ['<', 'startedAt', Db::prepareDateForDb((clone $now)->sub(new DateInterval('P' . self::KEEP_DAYS . 'D')))]);
    }


    // Private Methods
    // =========================================================================

    private function _isRegular(array $runs, string $trigger): bool
    {
        if (count($runs) < 3) {
            return false;
        }

        $times = [];

        foreach ($runs as $run) {
            if ($run['trigger'] !== $trigger) {
                return false;
            }

            $times[] = DateTimeHelper::toDateTime($run['startedAt'])->getTimestamp();
        }

        for ($i = 1, $count = count($times); $i < $count; $i++) {
            if ($times[$i - 1] - $times[$i] > self::REGULAR_GAP_MINUTES * 60) {
                return false;
            }
        }

        return true;
    }
}
