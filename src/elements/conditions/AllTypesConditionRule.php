<?php

namespace wsydney76\extras\elements\conditions;

use Craft;
use craft\elements\conditions\entries\TypeConditionRule;

/**
 * All Types Condition Rule element condition rule
 */
class AllTypesConditionRule extends TypeConditionRule
{
    function getLabel(): string
    {
        return Craft::t('_extras', 'All Entry Types (incl. nested)');
    }

    protected function options(): array
    {
        $types = collect(Craft::$app->entries->getAllEntryTypes())->sortBy('name');

        $options = [];
        foreach ($types as $type) {
            $options[$type->uid] = $type->name;
        }

        return $options;
    }

}
