<?php

namespace wsydney76\extras\records;

use craft\db\ActiveRecord;

class TransferHistoryRecord extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%extras_transferhistory}}';
    }
}
