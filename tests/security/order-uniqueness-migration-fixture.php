<?php
namespace craft\db {
    class FixtureDb
    {
        public array $rows = [];
        public array $indexes = [
            'legacy-order-index' => [
                'columns' => ['orderId'],
                'unique' => false,
            ],
        ];

        public function tableExists(string $table): bool
        {
            return true;
        }
    }

    class Migration
    {
        public FixtureDb $db;
        public array $operations = [];

        public function __construct()
        {
            $this->db = FixtureState::$db;
        }

        public function update(
            string $table,
            array $columns,
            string|array $condition = '',
            array $params = [],
            bool $updateTimestamp = true,
        ): void {
            $this->operations[] = ['update', $table, $columns, $condition, $updateTimestamp];

            foreach ($this->db->rows as &$row) {
                if ($row['id'] === $condition['id']) {
                    $row = array_merge($row, $columns);
                }
            }
        }

        public function delete(string $table, string|array $condition = '', array $params = []): void
        {
            $this->operations[] = ['delete', $table, $condition];
            $ids = $condition['id'];
            $this->db->rows = array_values(array_filter(
                $this->db->rows,
                static fn(array $row): bool => !in_array($row['id'], $ids, true),
            ));
        }

        public function dropIndexIfExists(string $table, array|string $columns, bool $unique = false): void
        {
            $this->operations[] = ['dropIndexIfExists', $table, $columns, $unique];

            foreach ($this->db->indexes as $name => $index) {
                if ($index['columns'] === $columns && $index['unique'] === $unique) {
                    unset($this->db->indexes[$name]);
                }
            }
        }

        public function createIndex(?string $name, string $table, array|string $columns, bool $unique = false): void
        {
            $this->operations[] = ['createIndex', $name, $table, $columns, $unique];
            $this->db->indexes[$name ?? 'generated-index'] = [
                'columns' => $columns,
                'unique' => $unique,
            ];
        }
    }

    class FixtureState
    {
        public static FixtureDb $db;
    }

    class Query
    {
        private array $select = [];
        private ?array $where = null;
        private array $orderBy = [];

        public function select(array $columns): self
        {
            $this->select = $columns;

            return $this;
        }

        public function from(string $table): self
        {
            return $this;
        }

        public function groupBy(array $columns): self
        {
            return $this;
        }

        public function having(string $condition): self
        {
            return $this;
        }

        public function where(array $condition): self
        {
            $this->where = $condition;

            return $this;
        }

        public function orderBy(array $columns): self
        {
            $this->orderBy = $columns;

            return $this;
        }

        public function column(FixtureDb $db): array
        {
            $counts = [];

            foreach ($db->rows as $row) {
                $counts[$row['orderId']] = ($counts[$row['orderId']] ?? 0) + 1;
            }

            return array_keys(array_filter($counts, static fn(int $count): bool => $count > 1));
        }

        public function all(FixtureDb $db): array
        {
            $rows = array_values(array_filter(
                $db->rows,
                fn(array $row): bool => $row['orderId'] === $this->where['orderId'],
            ));

            usort($rows, static fn(array $a, array $b): int => $a['id'] <=> $b['id']);

            return array_map(function(array $row): array {
                return array_intersect_key($row, array_flip($this->select));
            }, $rows);
        }
    }
}

namespace craft\helpers {
    use craft\db\FixtureDb;

    class Db
    {
        public static function findIndex(string $table, array|string $columns, bool $unique, FixtureDb $db): ?string
        {
            foreach ($db->indexes as $name => $index) {
                if ($index['columns'] === $columns && $index['unique'] === $unique) {
                    return $name;
                }
            }

            return null;
        }
    }

    class MigrationHelper
    {
    }
}

namespace verbb\abandonedcart {
    class AbandonedCart
    {
    }
}

namespace {
    require __DIR__ . '/../../src/migrations/Install.php';
    require __DIR__ . '/../../src/migrations/m261003_010000_unique_order_id.php';

    use craft\db\FixtureDb;
    use craft\db\FixtureState;
    use verbb\abandonedcart\migrations\Install;
    use verbb\abandonedcart\migrations\m261003_010000_unique_order_id;

    function check(string $label, bool $condition): void
    {
        if (!$condition) {
            throw new RuntimeException("Failed: {$label}");
        }

        echo "PASS: {$label}\n";
    }

    FixtureState::$db = new FixtureDb();
    FixtureState::$db->rows = [
        [
            'id' => 10,
            'orderId' => 100,
            'email' => 'original@example.com',
            'recipientKey' => 'original@example.com',
            'uid' => 'original-uid',
            'clicked' => false,
            'isScheduled' => true,
            'firstReminder' => true,
            'secondReminder' => false,
            'isRecovered' => false,
            'isSent' => true,
            'dateLastSent' => '2026-10-01 09:00:00',
            'dateCreated' => '2026-10-01 08:00:00',
            'dateUpdated' => '2026-10-01 09:00:00',
        ],
        [
            'id' => 11,
            'orderId' => 100,
            'email' => 'later@example.com',
            'recipientKey' => 'later@example.com',
            'uid' => 'later-uid',
            'clicked' => true,
            'isScheduled' => true,
            'firstReminder' => true,
            'secondReminder' => true,
            'isRecovered' => true,
            'isSent' => false,
            'dateLastSent' => '2026-10-02 09:00:00',
            'dateCreated' => '2026-10-01 07:00:00',
            'dateUpdated' => '2026-10-02 10:00:00',
        ],
        [
            'id' => 20,
            'orderId' => 200,
            'email' => 'unrelated@example.com',
            'recipientKey' => 'unrelated@example.com',
            'uid' => 'unrelated-uid',
            'clicked' => false,
            'isScheduled' => false,
            'firstReminder' => false,
            'secondReminder' => false,
            'isRecovered' => false,
            'isSent' => false,
            'dateLastSent' => null,
            'dateCreated' => '2026-10-02 08:00:00',
            'dateUpdated' => '2026-10-02 08:00:00',
        ],
    ];

    $migration = new m261003_010000_unique_order_id();
    check('migration applies successfully', $migration->safeUp());
    check('duplicate order rows are consolidated', count(FixtureState::$db->rows) === 2);

    $canonical = array_values(array_filter(
        FixtureState::$db->rows,
        static fn(array $row): bool => $row['orderId'] === 100,
    ))[0];

    check('the oldest row remains the canonical queue identity', $canonical['id'] === 10 && $canonical['uid'] === 'original-uid');
    check('canonical recipient details are retained', $canonical['email'] === 'original@example.com' && $canonical['recipientKey'] === 'original@example.com');
    check('monotone reminder and recovery evidence is merged', $canonical['clicked'] && $canonical['firstReminder'] && $canonical['secondReminder'] && $canonical['isRecovered'] && $canonical['isSent']);
    check('scheduling is reset so deleted queue identities cannot strand the row', !$canonical['isScheduled']);
    check('the latest recovery capability is retained', $canonical['dateLastSent'] === '2026-10-02 09:00:00');
    check('the complete activity window is retained', $canonical['dateCreated'] === '2026-10-01 07:00:00' && $canonical['dateUpdated'] === '2026-10-02 10:00:00');

    $operationNames = array_column($migration->operations, 0);
    check('duplicates are removed before the unique index is created', array_search('delete', $operationNames, true) < array_search('createIndex', $operationNames, true));
    check('the unique index exists before the foreign-key index is removed', array_search('createIndex', $operationNames, true) < array_search('dropIndexIfExists', $operationNames, true));
    check('the old non-unique index is removed', in_array(['dropIndexIfExists', '{{%abandonedcart_carts}}', ['orderId'], false], $migration->operations, true));
    check('a named unique order index is created', in_array(['createIndex', 'abandonedcart_carts_orderId_unq', '{{%abandonedcart_carts}}', ['orderId'], true], $migration->operations, true));

    $retry = new m261003_010000_unique_order_id();
    check('migration can be retried after its DDL has committed', $retry->safeUp());
    check('retry does not attempt to recreate the unique index', !in_array('createIndex', array_column($retry->operations, 0), true));
    check('irreversible duplicate consolidation cannot be rolled back', !$migration->safeDown());

    $install = new Install();
    $install->createIndexes();
    check('fresh installs create the same unique order index', $install->operations[0] === ['createIndex', 'abandonedcart_carts_orderId_unq', '{{%abandonedcart_carts}}', ['orderId'], true]);
}
