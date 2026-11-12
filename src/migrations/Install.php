<?php
namespace verbb\abandonedcart\migrations;

use verbb\abandonedcart\AbandonedCart;
use verbb\abandonedcart\db\Table;
use verbb\abandonedcart\models\EmailTemplate;
use verbb\abandonedcart\services\EmailTemplates;

use Craft;
use craft\db\Migration;
use craft\db\Table as CraftTable;

use RuntimeException;

use craft\commerce\db\Table as CommerceTable;

class Install extends Migration
{
    // Public Methods
    // =========================================================================

    public function safeUp(): bool
    {
        $this->createTables();
        $this->createIndexes();
        $this->addForeignKeys();
        $this->insertDefaultData();

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTables();

        Craft::$app->getProjectConfig()->remove(EmailTemplates::CONFIG_KEY_ROOT);

        return true;
    }

    public function createTables(): void
    {
        $this->archiveTableIfExists(Table::SEQUENCES);
        $this->createTable(Table::SEQUENCES, [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'handle' => $this->string()->notNull(),
            'sortOrder' => $this->smallInteger()->notNull()->defaultValue(0),
            'isFallback' => $this->boolean()->notNull()->defaultValue(false),
            'enabled' => $this->boolean()->notNull()->defaultValue(false),
            'siteIds' => $this->text(),
            'audienceType' => $this->string()->notNull(),
            'audienceSettings' => $this->text(),
            'abandonedAfterMinutes' => $this->integer(),
            'recoveryWindowMinutes' => $this->integer(),
            'attributionWindowMinutes' => $this->integer(),
            'contactLimitMinutes' => $this->integer(),
            'returnUrl' => $this->string(1024),
            'enrollmentStartedAt' => $this->dateTime(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->archiveTableIfExists(Table::SEQUENCE_VERSIONS);
        $this->createTable(Table::SEQUENCE_VERSIONS, [
            'id' => $this->primaryKey(),
            'sequenceId' => $this->integer(),
            'version' => $this->integer()->notNull(),
            'snapshot' => $this->text()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->archiveTableIfExists(Table::EMAIL_TEMPLATES);
        $this->createTable(Table::EMAIL_TEMPLATES, [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'handle' => $this->string()->notNull(),
            'htmlTemplate' => $this->string(500),
            'textTemplate' => $this->string(500),
            'isDefault' => $this->boolean()->notNull()->defaultValue(false),
            'sortOrder' => $this->smallInteger()->notNull()->defaultValue(0),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->archiveTableIfExists(Table::EMAILS);
        $this->createTable(Table::EMAILS, [
            'id' => $this->primaryKey(),
            'sequenceId' => $this->integer()->notNull(),
            'position' => $this->smallInteger()->notNull(),
            'delayMinutes' => $this->integer()->notNull()->defaultValue(0),
            'subject' => $this->string()->notNull(),
            'previewText' => $this->string(),
            'heading' => $this->string(),
            'message' => $this->text(),
            'buttonLabel' => $this->string(),
            'discountCode' => $this->string(),
            'emailTemplateId' => $this->integer(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->archiveTableIfExists(Table::CUSTOMERS);
        $this->createTable(Table::CUSTOMERS, [
            'id' => $this->primaryKey(),
            'email' => $this->string()->notNull(),
            'userId' => $this->integer(),
            'status' => $this->string()->notNull()->defaultValue('active'),
            'statusSource' => $this->string(),
            'statusNote' => $this->text(),
            'statusChangedById' => $this->integer(),
            'statusChangedAt' => $this->dateTime(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->archiveTableIfExists(Table::RECOVERIES);
        $this->createTable(Table::RECOVERIES, [
            'id' => $this->primaryKey(),
            'orderId' => $this->integer(),
            'customerId' => $this->integer()->notNull(),
            'siteId' => $this->integer(),
            'sequenceId' => $this->integer(),
            'sequenceVersionId' => $this->integer(),
            'status' => $this->string()->notNull()->defaultValue('active'),
            'endReason' => $this->string(),
            'cartTotal' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'currency' => $this->string(3),
            'attributionWindowMinutes' => $this->integer()->notNull(),
            'abandonedAt' => $this->dateTime()->notNull(),
            'expiresAt' => $this->dateTime()->notNull(),
            'endedAt' => $this->dateTime(),
            'recoveredAt' => $this->dateTime(),
            'recoveredOrderId' => $this->integer(),
            'creditedAttemptId' => $this->integer(),
            'clickedBeforeOrder' => $this->boolean()->notNull()->defaultValue(false),
            'orderTotal' => $this->decimal(14, 4),
            'isFirstOrder' => $this->boolean(),
            'couponCode' => $this->string(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->archiveTableIfExists(Table::ATTEMPTS);
        $this->createTable(Table::ATTEMPTS, [
            'id' => $this->primaryKey(),
            'recoveryId' => $this->integer()->notNull(),
            'emailId' => $this->integer(),
            'position' => $this->smallInteger()->notNull(),
            'status' => $this->string()->notNull()->defaultValue('scheduled'),
            'dueAt' => $this->dateTime()->notNull(),
            'sentAt' => $this->dateTime(),
            'subject' => $this->string(),
            'tries' => $this->smallInteger()->notNull()->defaultValue(0),
            'leaseToken' => $this->string(64),
            'leaseExpiresAt' => $this->dateTime(),
            'failureReason' => $this->string(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->archiveTableIfExists(Table::VISITS);
        $this->createTable(Table::VISITS, [
            'id' => $this->primaryKey(),
            'recoveryId' => $this->integer()->notNull(),
            'attemptId' => $this->integer(),
            'result' => $this->string()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->archiveTableIfExists(Table::ACTIVITIES);
        $this->createTable(Table::ACTIVITIES, [
            'id' => $this->primaryKey(),
            'recoveryId' => $this->integer()->notNull(),
            'attemptId' => $this->integer(),
            'type' => $this->string()->notNull(),
            'data' => $this->text(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->archiveTableIfExists(Table::SKIPPED_CARTS);
        $this->createTable(Table::SKIPPED_CARTS, [
            'id' => $this->primaryKey(),
            'orderId' => $this->integer(),
            'customerId' => $this->integer(),
            'siteId' => $this->integer(),
            'reason' => $this->string()->notNull(),
            'detail' => $this->string(),
            'cartTotal' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'currency' => $this->string(3),
            'abandonedAt' => $this->dateTime()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createRunsTable();

        $this->archiveTableIfExists(Table::STATE);
        $this->createTable(Table::STATE, [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'value' => $this->text(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);
    }

    public function createIndexes(): void
    {
        $this->createIndex(null, Table::SEQUENCES, ['handle'], true);
        $this->createIndex(null, Table::SEQUENCES, ['isFallback', 'sortOrder']);
        $this->createIndex(null, Table::SEQUENCE_VERSIONS, ['sequenceId', 'version'], true);
        $this->createIndex(null, Table::EMAIL_TEMPLATES, ['handle'], true);
        $this->createIndex(null, Table::EMAILS, ['sequenceId', 'position'], true);
        $this->createIndex(null, Table::EMAILS, ['emailTemplateId']);
        $this->createIndex(null, Table::CUSTOMERS, ['email'], true);
        $this->createIndex(null, Table::CUSTOMERS, ['userId']);
        $this->createIndex(null, Table::CUSTOMERS, ['status']);
        $this->createIndex(null, Table::RECOVERIES, ['orderId']);
        $this->createIndex(null, Table::RECOVERIES, ['customerId']);
        $this->createIndex(null, Table::RECOVERIES, ['sequenceId']);
        $this->createIndex(null, Table::RECOVERIES, ['status', 'expiresAt']);
        $this->createIndex(null, Table::RECOVERIES, ['abandonedAt']);
        $this->createIndex(null, Table::ATTEMPTS, ['recoveryId', 'position'], true);
        $this->createIndex(null, Table::ATTEMPTS, ['status', 'dueAt']);
        $this->createIndex(null, Table::ATTEMPTS, ['emailId']);
        $this->createIndex(null, Table::VISITS, ['recoveryId']);
        $this->createIndex(null, Table::ACTIVITIES, ['recoveryId']);
        $this->createIndex(null, Table::SKIPPED_CARTS, ['orderId']);
        $this->createIndex(null, Table::SKIPPED_CARTS, ['customerId']);
        $this->createIndex(null, Table::SKIPPED_CARTS, ['reason', 'abandonedAt']);
        $this->createIndex(null, Table::STATE, ['name'], true);
        $this->createIndex(null, Table::RUNS, ['startedAt']);
    }

    /**
     * One row per scheduler run, for System Status. Kept for 7 days.
     */
    public function createRunsTable(): void
    {
        $this->archiveTableIfExists(Table::RUNS);
        $this->createTable(Table::RUNS, [
            'id' => $this->primaryKey(),
            'trigger' => $this->string(20)->notNull(),
            'startedAt' => $this->dateTime()->notNull(),
            'durationMs' => $this->integer()->notNull()->defaultValue(0),
            'enrolled' => $this->integer()->notNull()->defaultValue(0),
            'skipped' => $this->integer()->notNull()->defaultValue(0),
            'sent' => $this->integer()->notNull()->defaultValue(0),
            'failed' => $this->integer()->notNull()->defaultValue(0),
            'notSent' => $this->integer()->notNull()->defaultValue(0),
            'ended' => $this->integer()->notNull()->defaultValue(0),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);
    }

    public function addForeignKeys(): void
    {
        // Sequences and emails can be deleted while their history stays, so history keeps the row and loses the link.
        $this->addForeignKey(null, Table::SEQUENCE_VERSIONS, ['sequenceId'], Table::SEQUENCES, ['id'], 'SET NULL', null);
        $this->addForeignKey(null, Table::EMAILS, ['sequenceId'], Table::SEQUENCES, ['id'], 'CASCADE', null);
        $this->addForeignKey(null, Table::EMAILS, ['emailTemplateId'], Table::EMAIL_TEMPLATES, ['id'], 'SET NULL', null);
        $this->addForeignKey(null, Table::CUSTOMERS, ['userId'], CraftTable::USERS, ['id'], 'SET NULL', null);
        $this->addForeignKey(null, Table::CUSTOMERS, ['statusChangedById'], CraftTable::USERS, ['id'], 'SET NULL', null);

        // Commerce purges old carts, and recovery history has to outlive them.
        $this->addForeignKey(null, Table::RECOVERIES, ['orderId'], CommerceTable::ORDERS, ['id'], 'SET NULL', null);
        $this->addForeignKey(null, Table::RECOVERIES, ['recoveredOrderId'], CommerceTable::ORDERS, ['id'], 'SET NULL', null);
        $this->addForeignKey(null, Table::RECOVERIES, ['customerId'], Table::CUSTOMERS, ['id'], 'CASCADE', null);
        $this->addForeignKey(null, Table::RECOVERIES, ['siteId'], CraftTable::SITES, ['id'], 'SET NULL', null);
        $this->addForeignKey(null, Table::RECOVERIES, ['sequenceId'], Table::SEQUENCES, ['id'], 'SET NULL', null);
        $this->addForeignKey(null, Table::RECOVERIES, ['sequenceVersionId'], Table::SEQUENCE_VERSIONS, ['id'], 'SET NULL', null);
        $this->addForeignKey(null, Table::ATTEMPTS, ['recoveryId'], Table::RECOVERIES, ['id'], 'CASCADE', null);
        $this->addForeignKey(null, Table::ATTEMPTS, ['emailId'], Table::EMAILS, ['id'], 'SET NULL', null);
        $this->addForeignKey(null, Table::VISITS, ['recoveryId'], Table::RECOVERIES, ['id'], 'CASCADE', null);
        $this->addForeignKey(null, Table::VISITS, ['attemptId'], Table::ATTEMPTS, ['id'], 'SET NULL', null);
        $this->addForeignKey(null, Table::ACTIVITIES, ['recoveryId'], Table::RECOVERIES, ['id'], 'CASCADE', null);
        $this->addForeignKey(null, Table::ACTIVITIES, ['attemptId'], Table::ATTEMPTS, ['id'], 'SET NULL', null);
        $this->addForeignKey(null, Table::SKIPPED_CARTS, ['orderId'], CommerceTable::ORDERS, ['id'], 'SET NULL', null);
        $this->addForeignKey(null, Table::SKIPPED_CARTS, ['customerId'], Table::CUSTOMERS, ['id'], 'SET NULL', null);
        $this->addForeignKey(null, Table::SKIPPED_CARTS, ['siteId'], CraftTable::SITES, ['id'], 'SET NULL', null);
    }

    public function insertDefaultData(): void
    {
        $emailTemplates = AbandonedCart::$plugin->getEmailTemplates();

        // Installing while applying project config from another environment brings the templates with it.
        if (!Craft::$app->getProjectConfig()->getIsApplyingExternalChanges() && !$emailTemplates->getDefaultTemplate()) {
            $template = new EmailTemplate([
                'name' => Craft::t('abandoned-cart', 'Default'),
                'handle' => 'default',
                'isDefault' => true,
            ]);

            if (!$emailTemplates->saveTemplate($template)) {
                throw new RuntimeException('Unable to create the default email template.');
            }
        }

        AbandonedCart::$plugin->getPresets()->installBuiltInPresets();
        AbandonedCart::$plugin->getSequences()->createFallbackSequence();
    }

    public function dropTables(): void
    {
        // Children first, so no foreign key outlives the table it points at. The 4.x table only exists on upgraded installs.
        foreach ([...self::_tables(), Table::CARTS] as $table) {
            $this->dropTableIfExists($table);
        }
    }


    // Private Methods
    // =========================================================================

    private static function _tables(): array
    {
        return [
            Table::ACTIVITIES,
            Table::VISITS,
            Table::ATTEMPTS,
            Table::SKIPPED_CARTS,
            Table::RECOVERIES,
            Table::CUSTOMERS,
            Table::EMAILS,
            Table::EMAIL_TEMPLATES,
            Table::SEQUENCE_VERSIONS,
            Table::SEQUENCES,
            Table::STATE,
            Table::RUNS,
        ];
    }
}
