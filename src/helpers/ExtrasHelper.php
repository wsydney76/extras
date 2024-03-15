<?php

namespace wsydney76\extras\helpers;

use Craft;
use craft\base\Component;
use craft\errors\EntryTypeNotFoundException;
use craft\errors\InvalidFieldException;

class ExtrasHelper extends Component
{
    public static function getValueSql(string $entryTypeHandle, string $fieldHandle): string
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