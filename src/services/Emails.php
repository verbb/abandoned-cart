<?php
namespace verbb\abandonedcart\services;

use verbb\abandonedcart\AbandonedCart;
use verbb\abandonedcart\db\Table;
use verbb\abandonedcart\events\EmailEvent;
use verbb\abandonedcart\models\Email;
use verbb\abandonedcart\records\Email as EmailRecord;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\helpers\Db;

use Throwable;
use yii\base\InvalidArgumentException;
use yii\db\Expression;

class Emails extends Component
{
    // Constants
    // =========================================================================

    public const EVENT_BEFORE_SAVE_EMAIL = 'beforeSaveEmail';
    public const EVENT_AFTER_SAVE_EMAIL = 'afterSaveEmail';
    public const EVENT_BEFORE_DELETE_EMAIL = 'beforeDeleteEmail';
    public const EVENT_AFTER_DELETE_EMAIL = 'afterDeleteEmail';


    // Public Methods
    // =========================================================================

    public function getEmailsBySequenceId(int $sequenceId): array
    {
        $rows = $this->_createEmailQuery()->where(['sequenceId' => $sequenceId])->all();

        return array_map(static fn(array $row): Email => new Email($row), $rows);
    }

    public function getEmailById(int $id): ?Email
    {
        $row = $this->_createEmailQuery()->where(['id' => $id])->one();

        return $row ? new Email($row) : null;
    }

    public function saveEmail(Email $email, bool $runValidation = true): bool
    {
        $isNew = !$email->id;

        if ($this->hasEventHandlers(self::EVENT_BEFORE_SAVE_EMAIL)) {
            $event = new EmailEvent(['email' => $email, 'isNew' => $isNew]);
            $this->trigger(self::EVENT_BEFORE_SAVE_EMAIL, $event);

            if (!$event->isValid) {
                return false;
            }
        }

        if ($runValidation && !$email->validate()) {
            return false;
        }

        $sequence = $email->getSequence();

        if (!$sequence) {
            $email->addError('sequenceId', Craft::t('abandoned-cart', 'The sequence for this email doesn’t exist.'));

            return false;
        }

        $count = (int)(new Query())->from([Table::EMAILS])->where(['sequenceId' => $email->sequenceId])->count();

        if ($isNew && $count >= Email::MAX_PER_SEQUENCE) {
            $email->addError('position', Craft::t('abandoned-cart', 'A sequence can have up to {max} emails.', ['max' => Email::MAX_PER_SEQUENCE]));

            return false;
        }

        $transaction = Craft::$app->getDb()->beginTransaction();

        try {
            if ($isNew) {
                $last = $count + 1;
                $email->position = min($email->position ?? $last, $last);

                // Adding between two emails moves the later ones down, so their gaps stay the same.
                $this->_shiftPositions($email->sequenceId, $email->position, 1);
                $record = new EmailRecord();
            } else {
                $record = $this->_getEmailRecordById($email->id);
                $email->position = (int)$record->position;
            }

            $record->sequenceId = $email->sequenceId;
            $record->position = $email->position;
            $record->delayMinutes = $email->delayMinutes;
            $record->subject = $email->subject;
            $record->previewText = $email->previewText;
            $record->heading = $email->heading;
            $record->message = $email->message;
            $record->buttonLabel = $email->buttonLabel;
            $record->discountCode = $email->discountCode ?: null;
            $record->emailTemplateId = $email->emailTemplateId;
            $record->save(false);

            $email->id = $record->id;
            $email->uid = $record->uid;

            $transaction->commit();
        } catch (Throwable $e) {
            $transaction->rollBack();

            throw $e;
        }

        if ($this->hasEventHandlers(self::EVENT_AFTER_SAVE_EMAIL)) {
            $this->trigger(self::EVENT_AFTER_SAVE_EMAIL, new EmailEvent(['email' => $email, 'isNew' => $isNew]));
        }

        return true;
    }

    public function deleteEmailById(int $id): bool
    {
        $email = $this->getEmailById($id);

        return $email && $this->deleteEmail($email);
    }

    public function deleteEmail(Email $email): bool
    {
        if ($this->hasEventHandlers(self::EVENT_BEFORE_DELETE_EMAIL)) {
            $event = new EmailEvent(['email' => $email]);
            $this->trigger(self::EVENT_BEFORE_DELETE_EMAIL, $event);

            if (!$event->isValid) {
                return false;
            }
        }

        $transaction = Craft::$app->getDb()->beginTransaction();

        try {
            Db::delete(Table::EMAILS, ['id' => $email->id]);
            $this->_shiftPositions($email->sequenceId, $email->position + 1, -1);

            $transaction->commit();
        } catch (Throwable $e) {
            $transaction->rollBack();

            throw $e;
        }

        if ($this->hasEventHandlers(self::EVENT_AFTER_DELETE_EMAIL)) {
            $this->trigger(self::EVENT_AFTER_DELETE_EMAIL, new EmailEvent(['email' => $email]));
        }

        return true;
    }


    // Private Methods
    // =========================================================================

    private function _shiftPositions(int $sequenceId, int $fromPosition, int $by): void
    {
        $ids = (new Query())
            ->select(['id'])
            ->from([Table::EMAILS])
            ->where(['sequenceId' => $sequenceId])
            ->andWhere(['>=', 'position', $fromPosition])
            // Move the far end first, so no two emails ever share a position under the unique index.
            ->orderBy(['position' => $by > 0 ? SORT_DESC : SORT_ASC])
            ->column();

        foreach ($ids as $id) {
            Db::update(Table::EMAILS, ['position' => new Expression('[[position]] + :by', ['by' => $by])], ['id' => $id]);
        }
    }

    private function _createEmailQuery(): Query
    {
        return (new Query())
            ->select([
                'id',
                'sequenceId',
                'position',
                'delayMinutes',
                'subject',
                'previewText',
                'heading',
                'message',
                'buttonLabel',
                'discountCode',
                'emailTemplateId',
                'dateCreated',
                'dateUpdated',
                'uid',
            ])
            ->from([Table::EMAILS])
            ->orderBy(['sequenceId' => SORT_ASC, 'position' => SORT_ASC]);
    }

    private function _getEmailRecordById(int $id): EmailRecord
    {
        $record = EmailRecord::findOne($id);

        if (!$record) {
            throw new InvalidArgumentException("No email exists with the ID “{$id}”.");
        }

        return $record;
    }
}
