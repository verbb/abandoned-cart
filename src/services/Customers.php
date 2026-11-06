<?php
namespace verbb\abandonedcart\services;

use verbb\abandonedcart\AbandonedCart;
use verbb\abandonedcart\db\Table;
use verbb\abandonedcart\events\CustomerEvent;
use verbb\abandonedcart\events\CustomerStatusEvent;
use verbb\abandonedcart\models\Customer;
use verbb\abandonedcart\models\Recovery;
use verbb\abandonedcart\records\Customer as CustomerRecord;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\helpers\Db;

use DateTime;
use yii\base\InvalidArgumentException;

class Customers extends Component
{
    // Constants
    // =========================================================================

    public const EVENT_BEFORE_SAVE_CUSTOMER = 'beforeSaveCustomer';
    public const EVENT_AFTER_SAVE_CUSTOMER = 'afterSaveCustomer';
    public const EVENT_BEFORE_CHANGE_STATUS = 'beforeChangeStatus';
    public const EVENT_AFTER_CHANGE_STATUS = 'afterChangeStatus';


    // Public Methods
    // =========================================================================

    public function getCustomerById(int $id): ?Customer
    {
        $row = $this->_createCustomerQuery()->where(['id' => $id])->one();

        return $row ? new Customer($row) : null;
    }

    public function getCustomerByEmail(string $email): ?Customer
    {
        $row = $this->_createCustomerQuery()->where(['email' => Customer::normalizeEmail($email)])->one();

        return $row ? new Customer($row) : null;
    }

    public function getCustomerByUser(\craft\elements\User $user): ?Customer
    {
        $row = $this->_createCustomerQuery()->where(['userId' => $user->id])->one();

        if (!$row && $user->email) {
            $row = $this->_createCustomerQuery()->where(['email' => Customer::normalizeEmail($user->email)])->one();
        }

        return $row ? new Customer($row) : null;
    }

    public function getOrCreateCustomer(string $email, ?int $userId = null): Customer
    {
        $customer = $this->getCustomerByEmail($email);

        if ($customer) {
            // Guests become users when they check out or register, so link the account once there is one.
            if ($userId && $customer->userId !== $userId) {
                $customer->userId = $userId;
                $this->saveCustomer($customer, false);
            }

            return $customer;
        }

        $customer = new Customer(['email' => $email, 'userId' => $userId]);

        if (!$this->saveCustomer($customer)) {
            throw new InvalidArgumentException('Unable to save the customer: ' . implode(' ', $customer->getFirstErrors()));
        }

        return $customer;
    }

    public function saveCustomer(Customer $customer, bool $runValidation = true): bool
    {
        $isNew = !$customer->id;
        $customer->email = Customer::normalizeEmail((string)$customer->email);

        if ($this->hasEventHandlers(self::EVENT_BEFORE_SAVE_CUSTOMER)) {
            $event = new CustomerEvent(['customer' => $customer, 'isNew' => $isNew]);
            $this->trigger(self::EVENT_BEFORE_SAVE_CUSTOMER, $event);

            if (!$event->isValid) {
                return false;
            }
        }

        if ($runValidation && !$customer->validate()) {
            return false;
        }

        $record = $isNew ? new CustomerRecord() : $this->_getCustomerRecordById($customer->id);
        $record->email = $customer->email;
        $record->userId = $customer->userId;
        $record->status = $customer->status;
        $record->statusSource = $customer->statusSource;
        $record->statusNote = $customer->statusNote;
        $record->statusChangedById = $customer->statusChangedById;
        $record->statusChangedAt = Db::prepareDateForDb($customer->statusChangedAt);
        $record->save(false);

        $customer->id = $record->id;
        $customer->uid = $record->uid;

        if ($this->hasEventHandlers(self::EVENT_AFTER_SAVE_CUSTOMER)) {
            $this->trigger(self::EVENT_AFTER_SAVE_CUSTOMER, new CustomerEvent(['customer' => $customer, 'isNew' => $isNew]));
        }

        return true;
    }

    public function changeStatus(Customer $customer, string $status, ?string $source = null, ?string $note = null): bool
    {
        if ($this->hasEventHandlers(self::EVENT_BEFORE_CHANGE_STATUS)) {
            $event = new CustomerStatusEvent(['customer' => $customer, 'status' => $status, 'source' => $source, 'note' => $note]);
            $this->trigger(self::EVENT_BEFORE_CHANGE_STATUS, $event);

            if (!$event->isValid) {
                return false;
            }
        }

        // Only the customer can undo an unsubscribe, by opting in again at checkout.
        if ($customer->status === Customer::STATUS_UNSUBSCRIBED && $status === Customer::STATUS_ACTIVE && $source !== Customer::SOURCE_CHECKOUT) {
            $customer->addError('status', Craft::t('abandoned-cart', 'Only the customer can undo an unsubscribe.'));

            return false;
        }

        $customer->status = $status;
        $customer->statusSource = $source;
        $customer->statusNote = $note;
        $customer->statusChangedById = Craft::$app->getUser()->getId();
        $customer->statusChangedAt = new DateTime();

        if (!$this->saveCustomer($customer)) {
            return false;
        }

        // Nobody who has asked not to be emailed should get the rest of a sequence already under way.
        if ($status !== Customer::STATUS_ACTIVE) {
            AbandonedCart::$plugin->getRecoveries()->endRecoveriesForCustomer($customer, $status === Customer::STATUS_UNSUBSCRIBED ? Recovery::END_UNSUBSCRIBED : Recovery::END_DO_NOT_EMAIL);
        }

        if ($this->hasEventHandlers(self::EVENT_AFTER_CHANGE_STATUS)) {
            $this->trigger(self::EVENT_AFTER_CHANGE_STATUS, new CustomerStatusEvent(['customer' => $customer, 'status' => $status, 'source' => $source, 'note' => $note]));
        }

        return true;
    }


    // Private Methods
    // =========================================================================

    private function _createCustomerQuery(): Query
    {
        return (new Query())
            ->select([
                'id',
                'email',
                'userId',
                'status',
                'statusSource',
                'statusNote',
                'statusChangedById',
                'statusChangedAt',
                'dateCreated',
                'dateUpdated',
                'uid',
            ])
            ->from([Table::CUSTOMERS]);
    }

    private function _getCustomerRecordById(int $id): CustomerRecord
    {
        $record = CustomerRecord::findOne($id);

        if (!$record) {
            throw new InvalidArgumentException("No customer exists with the ID “{$id}”.");
        }

        return $record;
    }
}
