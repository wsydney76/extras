<?php

namespace wsydney76\extras\variables;

use Craft;
use yii\base\InvalidCallException;

class ExtrasVariable
{
    public function getValueSqlForField(string $entryTypeHandle, string $fieldHandle): string
    {
        $entryType = Craft::$app->entries->getEntryTypeByHandle($entryTypeHandle);
        if (!$entryType) {
            throw new InvalidCallException("Entry type '{$entryTypeHandle}' not found");
        }

        $fieldLayout = $entryType->getFieldLayout();
        $field = $fieldLayout->getFieldByHandle($fieldHandle);

        if(!$field) {
            throw new InvalidCallException("Field '{$fieldHandle}' not found");
        }

        $sql = $field->getValueSql();
        if (!$sql) {
            throw new InvalidCallException("No SQL value for field '{$fieldHandle}'");
        }

        return $sql;

    }

}