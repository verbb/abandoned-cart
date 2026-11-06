<?php
namespace verbb\abandonedcart\records;

use verbb\abandonedcart\db\Table;

use craft\db\ActiveRecord;

class EmailTemplate extends ActiveRecord
{
    // Static Methods
    // =========================================================================

    public static function tableName(): string
    {
        return Table::EMAIL_TEMPLATES;
    }
}
