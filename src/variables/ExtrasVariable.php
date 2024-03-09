<?php

namespace wsydney76\extras\variables;

use Craft;
use craft\errors\EntryTypeNotFoundException;
use craft\errors\FieldNotFoundException;
use craft\errors\InvalidFieldException;
use wsydney76\extras\ExtrasPlugin;
use yii\base\InvalidCallException;

class ExtrasVariable
{
    public function getValueSqlForField(string $entryTypeHandle, string $fieldHandle): string
    {
        return ExtrasPlugin::getInstance()->contentService->getValueSqlForField($entryTypeHandle, $fieldHandle);

    }

}