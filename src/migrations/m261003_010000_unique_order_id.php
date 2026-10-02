<?php
namespace verbb\abandonedcart\migrations;

use craft\db\Migration;
use craft\db\Query;
use craft\helpers\Db;

class m261003_010000_unique_order_id extends Migration
{
    // Public Methods
    // =========================================================================

    public function safeUp(): bool
    {
        $table = '{{%abandonedcart_carts}}';

        if (!$this->db->tableExists($table)) {
            return true;
        }

        $duplicateOrderIds = (new Query())
            ->select(['orderId'])
            ->from($table)
            ->groupBy(['orderId'])
            ->having('COUNT(*) > 1')
            ->column($this->db);

        foreach ($duplicateOrderIds as $orderId) {
            $rows = (new Query())
                ->select([
                    'id',
                    'clicked',
                    'firstReminder',
                    'secondReminder',
                    'isRecovered',
                    'isSent',
                    'dateLastSent',
                    'dateCreated',
                    'dateUpdated',
                ])
                ->from($table)
                ->where(['orderId' => $orderId])
                ->orderBy(['id' => SORT_ASC])
                ->all($this->db);

            $canonical = array_shift($rows);

            if (!$canonical) {
                continue;
            }

            $merged = $canonical;

            foreach ($rows as $row) {
                foreach (['clicked', 'firstReminder', 'secondReminder', 'isRecovered', 'isSent'] as $attribute) {
                    $merged[$attribute] = (bool)$merged[$attribute] || (bool)$row[$attribute];
                }

                $merged['dateLastSent'] = $this->_latestDate($merged['dateLastSent'], $row['dateLastSent']);
                $merged['dateCreated'] = $this->_earliestDate($merged['dateCreated'], $row['dateCreated']);
                $merged['dateUpdated'] = $this->_latestDate($merged['dateUpdated'], $row['dateUpdated']);
            }

            $this->update(
                $table,
                [
                    'clicked' => $merged['clicked'],
                    'isScheduled' => false,
                    'firstReminder' => $merged['firstReminder'],
                    'secondReminder' => $merged['secondReminder'],
                    'isRecovered' => $merged['isRecovered'],
                    'isSent' => $merged['isSent'],
                    'dateLastSent' => $merged['dateLastSent'],
                    'dateCreated' => $merged['dateCreated'],
                    'dateUpdated' => $merged['dateUpdated'],
                ],
                ['id' => $canonical['id']],
                [],
                false,
            );

            $duplicateIds = array_column($rows, 'id');

            if ($duplicateIds) {
                $this->delete($table, ['id' => $duplicateIds]);
            }
        }

        if (Db::findIndex($table, ['orderId'], true, $this->db) === null) {
            $this->createIndex('abandonedcart_carts_orderId_unq', $table, ['orderId'], true);
        }

        $this->dropIndexIfExists($table, ['orderId'], false);

        return true;
    }

    public function safeDown(): bool
    {
        echo "m261003_010000_unique_order_id cannot be reverted.\n";

        return false;
    }


    // Private Methods
    // =========================================================================

    private function _earliestDate(?string $first, ?string $second): ?string
    {
        if ($first === null) {
            return $second;
        }

        if ($second === null) {
            return $first;
        }

        return min($first, $second);
    }

    private function _latestDate(?string $first, ?string $second): ?string
    {
        if ($first === null) {
            return $second;
        }

        if ($second === null) {
            return $first;
        }

        return max($first, $second);
    }
}
