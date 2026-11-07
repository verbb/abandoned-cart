<?php
namespace verbb\abandonedcart\services;

use verbb\abandonedcart\AbandonedCart;
use verbb\abandonedcart\db\Table;
use verbb\abandonedcart\events\SequenceEvent;
use verbb\abandonedcart\models\Sequence;
use verbb\abandonedcart\records\Sequence as SequenceRecord;
use verbb\abandonedcart\records\SequenceVersion as SequenceVersionRecord;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\helpers\Db;
use craft\helpers\Json;
use craft\helpers\StringHelper;

use DateTime;
use RuntimeException;
use Throwable;
use yii\base\InvalidArgumentException;

class Sequences extends Component
{
    // Constants
    // =========================================================================

    public const EVENT_BEFORE_SAVE_SEQUENCE = 'beforeSaveSequence';
    public const EVENT_AFTER_SAVE_SEQUENCE = 'afterSaveSequence';
    public const EVENT_BEFORE_DELETE_SEQUENCE = 'beforeDeleteSequence';
    public const EVENT_AFTER_DELETE_SEQUENCE = 'afterDeleteSequence';


    // Public Methods
    // =========================================================================

    public function getAllSequences(): array
    {
        return $this->_populate($this->_createSequenceQuery()->all());
    }

    public function getEnabledSequences(): array
    {
        return $this->_populate($this->_createSequenceQuery()->where(['enabled' => true])->all());
    }

    public function getSequenceById(int $id): ?Sequence
    {
        $row = $this->_createSequenceQuery()->where(['id' => $id])->one();

        return $row ? new Sequence($row) : null;
    }

    public function getSequenceByHandle(string $handle): ?Sequence
    {
        $row = $this->_createSequenceQuery()->where(['handle' => $handle])->one();

        return $row ? new Sequence($row) : null;
    }

    public function getSequenceByUid(string $uid): ?Sequence
    {
        $row = $this->_createSequenceQuery()->where(['uid' => $uid])->one();

        return $row ? new Sequence($row) : null;
    }

    public function getFallbackSequence(): ?Sequence
    {
        $row = $this->_createSequenceQuery()->where(['isFallback' => true])->one();

        return $row ? new Sequence($row) : null;
    }

    public function saveSequence(Sequence $sequence, bool $runValidation = true): bool
    {
        $isNew = !$sequence->id;

        if ($this->hasEventHandlers(self::EVENT_BEFORE_SAVE_SEQUENCE)) {
            $event = new SequenceEvent(['sequence' => $sequence, 'isNew' => $isNew]);
            $this->trigger(self::EVENT_BEFORE_SAVE_SEQUENCE, $event);

            if (!$event->isValid) {
                return false;
            }
        }

        if (!$sequence->handle && $sequence->name) {
            $sequence->handle = $this->generateHandle($sequence->name, $sequence->id);
        }

        if ($runValidation && !$sequence->validate()) {
            return false;
        }

        $record = $isNew ? new SequenceRecord() : $this->_getSequenceRecordById($sequence->id);

        // Turning a sequence on only picks up carts abandoned from then, never a backlog from while it was off.
        if ($sequence->enabled && ($isNew || !$record->enabled)) {
            $sequence->enrollmentStartedAt = new DateTime();
        }

        if ($isNew && !$sequence->isFallback) {
            $sequence->sortOrder = (int)(new Query())->from([Table::SEQUENCES])->where(['isFallback' => false])->max('[[sortOrder]]') + 1;
        }

        $transaction = Craft::$app->getDb()->beginTransaction();

        try {
            $record->name = $sequence->name;
            $record->handle = $sequence->handle;
            $record->sortOrder = $sequence->isFallback ? 0 : $sequence->sortOrder;
            $record->isFallback = $sequence->isFallback;
            $record->enabled = $sequence->enabled;
            $record->siteIds = $sequence->siteIds ? Json::encode(array_values(array_map('intval', $sequence->siteIds))) : null;
            $record->audienceType = $sequence->audienceType;
            $record->audienceSettings = $sequence->audienceSettings ? Json::encode($sequence->audienceSettings) : null;
            $record->abandonedAfterMinutes = $sequence->abandonedAfterMinutes;
            $record->recoveryWindowMinutes = $sequence->recoveryWindowMinutes;
            $record->attributionWindowMinutes = $sequence->attributionWindowMinutes;
            $record->contactLimitMinutes = $sequence->contactLimitMinutes;
            $record->returnUrl = $sequence->returnUrl;
            $record->enrollmentStartedAt = Db::prepareDateForDb($sequence->enrollmentStartedAt);
            $record->save(false);

            $sequence->id = $record->id;
            $sequence->uid = $record->uid;

            $transaction->commit();
        } catch (Throwable $e) {
            $transaction->rollBack();

            throw $e;
        }

        if ($this->hasEventHandlers(self::EVENT_AFTER_SAVE_SEQUENCE)) {
            $this->trigger(self::EVENT_AFTER_SAVE_SEQUENCE, new SequenceEvent(['sequence' => $sequence, 'isNew' => $isNew]));
        }

        return true;
    }

    public function deleteSequenceById(int $id): bool
    {
        $sequence = $this->getSequenceById($id);

        return $sequence && $this->deleteSequence($sequence);
    }

    public function deleteSequence(Sequence $sequence): bool
    {
        if ($sequence->isFallback) {
            $sequence->addError('isFallback', Craft::t('abandoned-cart', 'The default sequence can’t be deleted. Turn it off instead.'));

            return false;
        }

        if ($this->hasEventHandlers(self::EVENT_BEFORE_DELETE_SEQUENCE)) {
            $event = new SequenceEvent(['sequence' => $sequence]);
            $this->trigger(self::EVENT_BEFORE_DELETE_SEQUENCE, $event);

            if (!$event->isValid) {
                return false;
            }
        }

        // Its emails go with it. Versions and cart history stay, without the link.
        Db::delete(Table::SEQUENCES, ['id' => $sequence->id]);

        if ($this->hasEventHandlers(self::EVENT_AFTER_DELETE_SEQUENCE)) {
            $this->trigger(self::EVENT_AFTER_DELETE_SEQUENCE, new SequenceEvent(['sequence' => $sequence]));
        }

        return true;
    }

    public function reorderSequences(array $ids): bool
    {
        $transaction = Craft::$app->getDb()->beginTransaction();

        try {
            foreach (array_values($ids) as $index => $id) {
                // The default sequence always stays last, whatever order is posted.
                Db::update(Table::SEQUENCES, ['sortOrder' => $index + 1], ['id' => (int)$id, 'isFallback' => false]);
            }

            $transaction->commit();
        } catch (Throwable $e) {
            $transaction->rollBack();

            throw $e;
        }

        return true;
    }

    public function createFallbackSequence(): Sequence
    {
        if ($existing = $this->getFallbackSequence()) {
            return $existing;
        }

        $presets = AbandonedCart::$plugin->getPresets();
        $preset = $presets->getPresetByHandle(Presets::PRESET_THREE_REMINDERS) ?? $presets->getBuiltInPresetByHandle(Presets::PRESET_THREE_REMINDERS);

        if (!$preset) {
            throw new RuntimeException('The Three Reminders preset is needed to create the default sequence.');
        }

        $sequence = $presets->createSequenceFromPreset($preset, [
            'name' => Craft::t('abandoned-cart', 'All Other Carts'),
            'handle' => 'everyoneElse',
            'isFallback' => true,
            'audienceType' => Sequence::AUDIENCE_ALL,
            'audienceSettings' => [],
        ]);

        if (!$sequence->id) {
            throw new RuntimeException('Unable to create the default sequence: ' . implode(' ', $sequence->getFirstErrors()));
        }

        return $sequence;
    }

    /**
     * Returns the version a cart entering this sequence now should keep: the latest one when timing, audience and the
     * email schedule are unchanged, or a new one when they've changed. Called at enrolment, so editing never creates
     * versions nobody uses.
     */
    public function getCurrentVersionId(Sequence $sequence): int
    {
        if (!$sequence->id) {
            throw new InvalidArgumentException('Only a saved sequence can have a version.');
        }

        $snapshot = Json::encode($sequence->getSnapshot(AbandonedCart::$plugin->getEmails()->getEmailsBySequenceId($sequence->id)));
        $latest = (new Query())
            ->select(['id', 'version', 'snapshot'])
            ->from([Table::SEQUENCE_VERSIONS])
            ->where(['sequenceId' => $sequence->id])
            ->orderBy(['version' => SORT_DESC])
            ->one();

        if ($latest && $latest['snapshot'] === $snapshot) {
            return (int)$latest['id'];
        }

        $record = new SequenceVersionRecord();
        $record->sequenceId = $sequence->id;
        $record->version = $latest ? (int)$latest['version'] + 1 : 1;
        $record->snapshot = $snapshot;
        $record->save(false);

        return $record->id;
    }

    public function generateHandle(string $name, ?int $ignoreId = null): string
    {
        $base = StringHelper::toCamelCase(StringHelper::toAscii($name)) ?: 'sequence';
        $base = preg_replace('/^[^a-zA-Z]+/', '', $base) ?: 'sequence';
        $handle = $base;
        $suffix = 1;

        while (($existing = $this->getSequenceByHandle($handle)) && $existing->id !== $ignoreId) {
            $handle = $base . ++$suffix;
        }

        return $handle;
    }


    // Private Methods
    // =========================================================================

    private function _populate(array $rows): array
    {
        return array_map(static fn(array $row): Sequence => new Sequence($row), $rows);
    }

    private function _createSequenceQuery(): Query
    {
        return (new Query())
            ->select([
                'id',
                'name',
                'handle',
                'sortOrder',
                'isFallback',
                'enabled',
                'siteIds',
                'audienceType',
                'audienceSettings',
                'abandonedAfterMinutes',
                'recoveryWindowMinutes',
                'attributionWindowMinutes',
                'contactLimitMinutes',
                'returnUrl',
                'enrollmentStartedAt',
                'dateCreated',
                'dateUpdated',
                'uid',
            ])
            ->from([Table::SEQUENCES])
            // Match order: the list from the top, then the default sequence.
            ->orderBy(['isFallback' => SORT_ASC, 'sortOrder' => SORT_ASC, 'id' => SORT_ASC]);
    }

    private function _getSequenceRecordById(int $id): SequenceRecord
    {
        $record = SequenceRecord::findOne($id);

        if (!$record) {
            throw new InvalidArgumentException("No sequence exists with the ID “{$id}”.");
        }

        return $record;
    }
}
