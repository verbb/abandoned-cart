<?php
namespace verbb\abandonedcart\records;

use verbb\abandonedcart\db\Table;

use craft\db\ActiveRecord;

class Sequence extends ActiveRecord
{
    // Static Methods
    // =========================================================================

    public static function tableName(): string
    {
        return Table::SEQUENCES;
    }
}
