<?php

namespace wsydney76\extras\variables;

use Craft;
use craft\errors\EntryTypeNotFoundException;
use craft\errors\FieldNotFoundException;
use craft\errors\InvalidFieldException;
use yii\base\InvalidCallException;

class ExtrasVariable
{
    public function getValueSqlForField(string $entryTypeHandle, string $fieldHandle): string
    {
        $entryType = Craft::$app->entries->getEntryTypeByHandle($entryTypeHandle);
        if (!$entryType) {
            throw new EntryTypeNotFoundException("Entry type '{$entryTypeHandle}' not found");
        }

        $fieldLayout = $entryType->getFieldLayout();
        $field = $fieldLayout->getFieldByHandle($fieldHandle);

        if(!$field) {
            throw new InvalidFieldException("Field '{$fieldHandle}' not found");
        }

        $sql = $field->getValueSql();
        if (!$sql) {
            throw new InvalidFieldException("Field '{$fieldHandle}' does not support JSON custom field queries");
        }

        return $sql;

    }

}