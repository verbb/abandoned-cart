<?php
namespace verbb\abandonedcart\migrations;

use verbb\abandonedcart\AbandonedCart;
use verbb\abandonedcart\db\Table;
use verbb\abandonedcart\models\Attempt;
use verbb\abandonedcart\models\Customer;
use verbb\abandonedcart\models\Email;
use verbb\abandonedcart\models\EmailTemplate;
use verbb\abandonedcart\models\Recovery;
use verbb\abandonedcart\models\Sequence;
use verbb\abandonedcart\models\Settings;
use verbb\abandonedcart\services\Activities;
use verbb\abandonedcart\services\EmailTemplates;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\helpers\App;
use craft\helpers\Db;
use craft\helpers\Json;
use craft\helpers\StringHelper;

use DateTime;
use DateTimeZone;
use RuntimeException;

use craft\commerce\db\Table as CommerceTable;

/**
 * Converts a 4.x install (Abandoned Cart 3.x on Craft 4, or 4.x on Craft 5) to 5.0.
 *
 * The 4.x settings become the Everyone Else sequence, email templates, Do Not Email statuses and the 5.0 settings,
 * and the 4.x reminder history is imported, so the Overview and customer history start with what 4.x sent.
 */
class m261106_000000_upgrade_to_5 extends Migration
{
    // Constants
    // =========================================================================

    public const BUNDLED_FIRST_TEMPLATE = 'abandoned-cart/emails/first';
    public const BUNDLED_SECOND_TEMPLATE = 'abandoned-cart/emails/second';

    // 4.x defaults, used where a 4.x setting was never saved.
    private const LEGACY_DEFAULTS = [
        'restoreExpiryHours' => 48,
        'firstReminderDelay' => 1,
        'secondReminderDelay' => 12,
        'recipientCooldownHours' => 24,
        'staleRecordRetentionDays' => 90,
        'firstReminderTemplate' => self::BUNDLED_FIRST_TEMPLATE,
        'secondReminderTemplate' => self::BUNDLED_SECOND_TEMPLATE,
        'firstReminderSubject' => 'You‘ve left some items in your cart',
        'secondReminderSubject' => 'Your items are still waiting - don‘t miss out',
        'recoveryUrl' => 'shop/cart',
        'disableSecondReminder' => false,
        'previousOrderRequired' => false,
        'blacklist' => null,
        'discountCode' => null,
    ];

    // A store counts as sending when 4.x sent a reminder this recently.
    private const SENDING_WITHIN_DAYS = 30;


    // Properties
    // =========================================================================

    private array $_legacy = [];
    private DateTime $_now;


    // Public Methods
    // =========================================================================

    public function safeUp(): bool
    {
        // A fresh 5.0 install creates everything itself and has nothing to convert.
        if (!$this->db->tableExists(Table::CARTS) && $this->db->tableExists(Table::SEQUENCES)) {
            return true;
        }

        $this->_now = new DateTime('now', new DateTimeZone('UTC'));
        $projectConfig = Craft::$app->getProjectConfig();

        // Read the 4.x settings before anything writes the 5.0 ones. On an environment that receives this upgrade
        // through project config, Craft's stored config still holds the 4.x settings while migrations run.
        // Values in config/abandoned-cart.php took precedence over the control panel in 4.x, so they win here too.
        $notNull = static fn($value): bool => $value !== null;
        $fileConfig = Craft::$app->getConfig()->getConfigFromFile('abandoned-cart');
        $fileConfig = is_array($fileConfig) ? $fileConfig : [];
        $this->_legacy = array_merge(
            self::LEGACY_DEFAULTS,
            array_filter((array)($projectConfig->get('plugins.abandoned-cart.settings') ?? []), $notNull),
            array_filter($fileConfig, $notNull),
        );

        if (!$this->db->tableExists(Table::SEQUENCES)) {
            $install = new Install(['db' => $this->db]);
            $install->createTables();
            $install->createIndexes();
            $install->addForeignKeys();
        }

        // Project config changes are only made once: where the incoming config already has 5.0, it brings the
        // templates and settings with it.
        $configIsCurrent = version_compare((string)$projectConfig->get('plugins.abandoned-cart.schemaVersion', true), '5.0.0', '>=');

        if ($configIsCurrent) {
            $projectConfig->processConfigChanges(EmailTemplates::CONFIG_KEY);
        } else {
            $this->_createEmailTemplates();
            $this->_saveSettings();
        }

        $sequence = $this->_createSequence();
        $this->_convertBlocklist();

        if ($this->db->tableExists(Table::CARTS)) {
            $this->_importHistory($sequence);
            $this->dropTable(Table::CARTS);
        }

        return true;
    }

    public function safeDown(): bool
    {
        echo "m261106_000000_upgrade_to_5 cannot be reverted.\n";

        return false;
    }


    // Private Methods
    // =========================================================================

    private function _legacyInt(string $key): int
    {
        return max(0, (int)App::parseEnv((string)$this->_legacy[$key]));
    }

    private function _legacyBool(string $key): bool
    {
        return (bool)App::parseBooleanEnv($this->_legacy[$key]);
    }

    private function _legacyString(string $key): ?string
    {
        $value = trim((string)($this->_legacy[$key] ?? ''));

        return $value !== '' ? $value : null;
    }

    /**
     * Email 1's wait after the cart is abandoned, and email 2's wait after email 1. Both 4.x delays were documented
     * as hours after the cart was abandoned, and both versions mark a cart abandoned after an hour without activity.
     */
    private function _delays(): array
    {
        $first = $this->_legacyInt('firstReminderDelay') * 60;
        $second = $this->_legacyInt('secondReminderDelay') * 60;

        return [$first, max(0, $second - $first)];
    }

    private function _templateUid(string $path): string
    {
        // Stable, so every environment that runs this upgrade agrees on the template.
        $hash = md5('abandoned-cart-4x-template:' . $path);

        return sprintf('%s-%s-%s-%s-%s', substr($hash, 0, 8), substr($hash, 8, 4), substr($hash, 12, 4), substr($hash, 16, 4), substr($hash, 20, 12));
    }

    private function _createEmailTemplates(): void
    {
        $emailTemplates = AbandonedCart::$plugin->getEmailTemplates();

        if (!$emailTemplates->getDefaultTemplate()) {
            $template = new EmailTemplate(['name' => Craft::t('abandoned-cart', 'Default'), 'handle' => 'default', 'isDefault' => true]);

            if (!$emailTemplates->saveTemplate($template)) {
                throw new RuntimeException('Unable to create the default email template: ' . implode(' ', $template->getFirstErrors()));
            }
        }

        // A custom 4.x template keeps working as an email template that points at it.
        foreach ($this->_customTemplatePaths() as $position => $path) {
            $uid = $this->_templateUid($path);

            if ($emailTemplates->getTemplateByUid($uid)) {
                continue;
            }

            $template = new EmailTemplate([
                'name' => Craft::t('abandoned-cart', '4.x Reminder {position}', ['position' => $position]),
                'handle' => StringHelper::toHandle('reminder' . $position . 'From4x'),
                'htmlTemplate' => $path,
            ]);

            $projectConfig = Craft::$app->getProjectConfig();
            $template->uid = $uid;
            $template->sortOrder = $position + 1;
            $projectConfig->set(EmailTemplates::CONFIG_KEY . '.' . $uid, $template->getConfig(), 'Convert the 4.x reminder templates');
        }
    }

    /**
     * Custom (not bundled) 4.x template paths, by reminder position. Two reminders sharing a path share a template.
     */
    private function _customTemplatePaths(): array
    {
        $paths = [];
        $first = $this->_legacyString('firstReminderTemplate');
        $second = $this->_legacyString('secondReminderTemplate');

        if ($first && $first !== self::BUNDLED_FIRST_TEMPLATE) {
            $paths[1] = $first;
        }

        if (!$this->_legacyBool('disableSecondReminder') && $second && $second !== self::BUNDLED_SECOND_TEMPLATE && $second !== $first) {
            $paths[2] = $second;
        }

        return $paths;
    }

    private function _templateIdForPath(?string $path): ?int
    {
        if (!$path || in_array($path, [self::BUNDLED_FIRST_TEMPLATE, self::BUNDLED_SECOND_TEMPLATE], true)) {
            return null;
        }

        $id = Db::idByUid(Table::EMAIL_TEMPLATES, $this->_templateUid($path));

        return $id ? (int)$id : null;
    }

    private function _saveSettings(): void
    {
        $plugin = AbandonedCart::$plugin;
        $settings = new Settings();
        $settings->pluginName = $this->_legacyString('pluginName') ?? $settings->pluginName;
        $settings->returnUrl = $this->_legacyString('recoveryUrl') ?? $settings->returnUrl;
        $settings->recoveryWindowMinutes = max(60, $this->_legacyInt('restoreExpiryHours') * 60);
        $settings->contactLimitMinutes = max(60, $this->_legacyInt('recipientCooldownHours') * 60);
        $settings->returningCustomersOnly = $this->_legacyBool('previousOrderRequired');
        $settings->passKey = $this->_legacyString('passKey');
        $settings->blockedDomains = $this->_blocklist()['domains'];

        if (isset($this->_legacy['maxEnrollmentsPerRun'])) {
            $settings->maxEnrollmentsPerRun = max(1, $this->_legacyInt('maxEnrollmentsPerRun'));
        }

        // 4.x kept unfinished carts for 90 days by default. A changed value carries over; the default gives way to 5.0's.
        $retention = $this->_legacyInt('staleRecordRetentionDays');

        if ($retention !== self::LEGACY_DEFAULTS['staleRecordRetentionDays']) {
            $settings->historyRetentionDays = max(Settings::MIN_HISTORY_RETENTION_DAYS, $retention);
        }

        if (!Craft::$app->getPlugins()->savePluginSettings($plugin, $settings->toArray())) {
            throw new RuntimeException('Unable to save the converted settings: ' . implode(' ', $plugin->getSettings()->getFirstErrors()));
        }
    }

    private function _blocklist(): array
    {
        $items = App::parseEnv((string)($this->_legacy['blacklist'] ?? ''));
        $items = array_filter(array_map(static fn(string $item): string => strtolower(trim($item)), explode(',', $items)));
        $addresses = [];
        $domains = [];

        foreach (array_unique($items) as $item) {
            if (str_starts_with($item, '@')) {
                $domains[] = substr($item, 1);
            } elseif (str_contains($item, '@')) {
                $addresses[] = $item;
            } else {
                $domains[] = $item;
            }
        }

        return ['addresses' => $addresses, 'domains' => array_values(array_unique(array_filter($domains)))];
    }

    private function _convertBlocklist(): void
    {
        $customers = AbandonedCart::$plugin->getCustomers();

        foreach ($this->_blocklist()['addresses'] as $address) {
            if (!filter_var($address, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $customer = $customers->getOrCreateCustomer($address);

            if ($customer->status !== Customer::STATUS_DO_NOT_EMAIL) {
                $customers->changeStatus($customer, Customer::STATUS_DO_NOT_EMAIL, Customer::SOURCE_UPGRADE, Craft::t('abandoned-cart', 'On the 4.x blocklist'));
            }
        }
    }

    private function _createSequence(): Sequence
    {
        $plugin = AbandonedCart::$plugin;
        $sequences = $plugin->getSequences();
        $emails = $plugin->getEmails();

        if ($existing = $sequences->getFallbackSequence()) {
            return $existing;
        }

        $sequence = $sequences->createFallbackSequence();
        [$firstDelay, $secondDelay] = $this->_delays();
        $discount = $this->_legacyString('discountCode');
        $positions = $this->_legacyBool('disableSecondReminder') ? 1 : 2;
        $settings = [
            1 => ['delay' => $firstDelay, 'subject' => $this->_legacyString('firstReminderSubject'), 'template' => $this->_legacyString('firstReminderTemplate')],
            2 => ['delay' => $secondDelay, 'subject' => $this->_legacyString('secondReminderSubject'), 'template' => $this->_legacyString('secondReminderTemplate')],
        ];

        // Removing from the end keeps positions stable.
        foreach (array_reverse($emails->getEmailsBySequenceId($sequence->id)) as $email) {
            if ($email->position > $positions) {
                $emails->deleteEmail($email);
            }
        }

        foreach ($emails->getEmailsBySequenceId($sequence->id) as $email) {
            $config = $settings[$email->position];
            $email->delayMinutes = $config['delay'];
            $email->subject = $config['subject'] ?? $email->subject;
            $email->discountCode = $discount;
            $email->emailTemplateId = $this->_templateIdForPath($config['template']);

            if (!$emails->saveEmail($email)) {
                throw new RuntimeException('Unable to convert reminder ' . $email->position . ': ' . implode(' ', $email->getFirstErrors()));
            }
        }

        // A store that was sending keeps sending; enrolment starts now, so older carts aren't swept in.
        if ($this->_wasSending()) {
            $sequence = $sequences->getSequenceById($sequence->id);
            $sequence->enabled = true;

            if (!$sequences->saveSequence($sequence)) {
                throw new RuntimeException('Unable to turn on Everyone Else: ' . implode(' ', $sequence->getFirstErrors()));
            }
        }

        return $sequences->getSequenceById($sequence->id);
    }

    private function _wasSending(): bool
    {
        if (!$this->db->tableExists(Table::CARTS)) {
            return false;
        }

        $since = Db::prepareDateForDb((clone $this->_now)->modify('-' . self::SENDING_WITHIN_DAYS . ' days'));

        return (new Query())
            ->from([Table::CARTS])
            ->where(['or', ['firstReminder' => true], ['secondReminder' => true], ['isSent' => true]])
            ->andWhere(['or', ['>=', 'dateLastSent', $since], ['and', ['dateLastSent' => null], ['>=', 'dateUpdated', $since]]])
            ->exists($this->db);
    }

    private function _importHistory(Sequence $sequence): void
    {
        $plugin = AbandonedCart::$plugin;
        $settings = $plugin->getSettings();
        $customers = $plugin->getCustomers();
        $emails = [];

        foreach ($plugin->getEmails()->getEmailsBySequenceId($sequence->id) as $email) {
            $emails[$email->position] = $email;
        }

        $versionId = $plugin->getSequences()->getCurrentVersionId($sequence);
        [$firstDelay, $secondDelay] = $this->_delays();
        $hasSecond = isset($emails[2]);
        $recoveryWindow = $this->_legacyInt('restoreExpiryHours') * 60 ?: $settings->recoveryWindowMinutes;
        $attributionWindow = $settings->attributionWindowMinutes;

        $rows = (new Query())
            ->select([
                'carts.*',
                'orderEmail' => 'orders.email',
                'orders.isCompleted',
                'orders.dateOrdered',
                'orders.totalPrice',
                'orders.currency',
                'orders.orderSiteId',
                'orders.customerId',
                'orders.number',
                'orders.reference',
                'orders.couponCode',
            ])
            ->from(['carts' => Table::CARTS])
            ->innerJoin(['orders' => CommerceTable::ORDERS], '[[orders.id]] = [[carts.orderId]]')
            ->where(['or', ['carts.firstReminder' => true], ['carts.secondReminder' => true], ['carts.isRecovered' => true]])
            ->orderBy(['carts.dateCreated' => SORT_ASC, 'carts.id' => SORT_ASC]);

        foreach ($rows->all($this->db) as $row) {
            $email = trim((string)($row['email'] ?: $row['orderEmail']));

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $customer = $customers->getOrCreateCustomer($email, $row['customerId'] ? (int)$row['customerId'] : null);
            $abandonedAt = $this->_date($row['dateCreated']);
            $lastSent = $row['dateLastSent'] ? $this->_date($row['dateLastSent']) : null;
            $sentFirst = (bool)$row['firstReminder'];
            $sentSecond = (bool)$row['secondReminder'];

            // 4.x only kept when the latest reminder went, so the first one's time is estimated when both went.
            $firstSentAt = $sentFirst ? (($sentSecond ? null : $lastSent) ?? (clone $abandonedAt)->modify("+$firstDelay minutes")) : null;
            $secondSentAt = $sentSecond ? ($lastSent ?? (clone ($firstSentAt ?? $abandonedAt))->modify("+$secondDelay minutes")) : null;
            $lastSentAt = $secondSentAt ?? $firstSentAt;

            $orderedAt = $row['isCompleted'] && $row['dateOrdered'] ? $this->_date($row['dateOrdered']) : null;
            $expiresAt = (clone $abandonedAt)->modify("+$recoveryWindow minutes");
            $status = Recovery::STATUS_ENDED;
            $endReason = null;
            $endedAt = null;

            if ($orderedAt) {
                $inWindow = $lastSentAt && $orderedAt >= $lastSentAt && $orderedAt <= (clone $lastSentAt)->modify("+$attributionWindow minutes");

                if ($row['isRecovered'] || $inWindow) {
                    $status = Recovery::STATUS_RECOVERED;
                } else {
                    $endReason = $lastSentAt ? Recovery::END_COMPLETED_OUTSIDE_WINDOW : Recovery::END_COMPLETED_BEFORE_EMAIL;
                    $endedAt = $orderedAt;
                }
            } elseif ($sentFirst && !$sentSecond && $hasSecond && $expiresAt > $this->_now) {
                // Part-way through 4.x: email 2 follows on, timed from the 4.x reminder.
                $status = Recovery::STATUS_ACTIVE;
            } else {
                $endReason = ($sentSecond || ($sentFirst && !$hasSecond)) ? Recovery::END_FINISHED : Recovery::END_EXPIRED;
                $endedAt = min($this->_now, $expiresAt);
            }

            $this->insert(Table::RECOVERIES, [
                'orderId' => (int)$row['orderId'],
                'customerId' => $customer->id,
                'siteId' => $row['orderSiteId'] ? (int)$row['orderSiteId'] : null,
                'sequenceId' => $sequence->id,
                'sequenceVersionId' => $versionId,
                'status' => $status,
                'endReason' => $endReason,
                'cartTotal' => (float)$row['totalPrice'],
                'currency' => $row['currency'],
                'attributionWindowMinutes' => $attributionWindow,
                'abandonedAt' => Db::prepareDateForDb($abandonedAt),
                'expiresAt' => Db::prepareDateForDb($expiresAt),
                'endedAt' => $endedAt ? Db::prepareDateForDb($endedAt) : null,
                'recoveredAt' => $status === Recovery::STATUS_RECOVERED ? Db::prepareDateForDb($orderedAt) : null,
                'clickedBeforeOrder' => $status === Recovery::STATUS_RECOVERED && $row['clicked'],
                'orderTotal' => $status === Recovery::STATUS_RECOVERED ? (float)$row['totalPrice'] : null,
                'couponCode' => $status === Recovery::STATUS_RECOVERED ? ($row['couponCode'] ?: null) : null,
                'dateCreated' => Db::prepareDateForDb($abandonedAt),
                'dateUpdated' => Db::prepareDateForDb($this->_now),
                'uid' => StringHelper::UUID(),
            ], false);
            $recoveryId = (int)$this->db->getLastInsertID();

            $this->_activity($recoveryId, Activities::TYPE_IMPORTED, $abandonedAt);

            $attemptIds = [];

            foreach ([1 => $firstSentAt, 2 => $secondSentAt] as $position => $sentAt) {
                if (!isset($emails[$position])) {
                    continue;
                }

                if ($sentAt) {
                    $attemptIds[$position] = $this->_attempt($recoveryId, $emails[$position], Attempt::STATUS_SENT, $sentAt, $sentAt);
                    $this->_activity($recoveryId, Activities::TYPE_SENT, $sentAt, $attemptIds[$position], ['position' => $position, 'subject' => $emails[$position]->subject]);
                } elseif ($status === Recovery::STATUS_ACTIVE) {
                    $this->_attempt($recoveryId, $emails[$position], Attempt::STATUS_SCHEDULED, (clone $firstSentAt)->modify("+$secondDelay minutes"), null);
                }
            }

            $creditedId = $attemptIds ? end($attemptIds) : null;

            if ($row['clicked'] && $creditedId) {
                $this->insert(Table::VISITS, [
                    'recoveryId' => $recoveryId,
                    'attemptId' => $creditedId,
                    'result' => 'restored',
                    'dateCreated' => Db::prepareDateForDb($lastSentAt),
                    'dateUpdated' => Db::prepareDateForDb($lastSentAt),
                    'uid' => StringHelper::UUID(),
                ], false);
            }

            if ($status === Recovery::STATUS_RECOVERED) {
                $this->update(Table::RECOVERIES, [
                    'creditedAttemptId' => $creditedId,
                    'isFirstOrder' => !$this->_hasEarlierOrder($email, (int)$row['orderId'], $orderedAt),
                ], ['id' => $recoveryId], [], false);
                $this->_activity($recoveryId, Activities::TYPE_RECOVERED, $orderedAt, $creditedId, ['number' => $row['reference'] ?: substr((string)$row['number'], 0, 7), 'position' => array_key_last($attemptIds)]);
            } elseif ($status === Recovery::STATUS_ENDED) {
                $this->_activity($recoveryId, Activities::TYPE_ENDED, $endedAt, null, ['reason' => Recovery::endReasonLabels()[$endReason] ?? $endReason]);
            }
        }
    }

    private function _attempt(int $recoveryId, Email $email, string $status, DateTime $dueAt, ?DateTime $sentAt): int
    {
        $this->insert(Table::ATTEMPTS, [
            'recoveryId' => $recoveryId,
            'emailId' => $email->id,
            'position' => $email->position,
            'status' => $status,
            'dueAt' => Db::prepareDateForDb($dueAt),
            'sentAt' => $sentAt ? Db::prepareDateForDb($sentAt) : null,
            'subject' => $sentAt ? $email->subject : null,
            'tries' => $sentAt ? 1 : 0,
            'dateCreated' => Db::prepareDateForDb($sentAt ?? $this->_now),
            'dateUpdated' => Db::prepareDateForDb($sentAt ?? $this->_now),
            'uid' => StringHelper::UUID(),
        ], false);

        return (int)$this->db->getLastInsertID();
    }

    private function _activity(int $recoveryId, string $type, DateTime $at, ?int $attemptId = null, array $data = []): void
    {
        $this->insert(Table::ACTIVITIES, [
            'recoveryId' => $recoveryId,
            'attemptId' => $attemptId,
            'type' => $type,
            'data' => $data ? Json::encode($data) : null,
            'dateCreated' => Db::prepareDateForDb($at),
            'dateUpdated' => Db::prepareDateForDb($at),
            'uid' => StringHelper::UUID(),
        ], false);
    }

    private function _hasEarlierOrder(string $email, int $orderId, DateTime $orderedAt): bool
    {
        return (new Query())
            ->from([CommerceTable::ORDERS])
            ->where(['isCompleted' => true, 'email' => $email])
            ->andWhere(['not', ['id' => $orderId]])
            ->andWhere(['<', 'dateOrdered', Db::prepareDateForDb($orderedAt)])
            ->exists($this->db);
    }

    private function _date(string $value): DateTime
    {
        return new DateTime($value, new DateTimeZone('UTC'));
    }
}
