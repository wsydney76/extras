<?php

namespace wsydney76\extras\utilities;

use Craft;
use craft\base\Utility;
use wsydney76\extras\ExtrasPlugin;
use function collect;

/**
 * Upgrade Inventory utility
 */
class UpgradeInventory extends Utility
{
    public static function displayName(): string
    {
        return Craft::t('_extras', 'Upgrade Inventory');
    }

    static function id(): string
    {
        return 'upgrade-inventory';
    }

    public static function icon(): ?string
    {
        return 'input-text';
    }

    static function contentHtml(): string
    {
        $fields = collect(Craft::$app->getFields()->getAllFields());

        $fieldTypes = $fields->map(function($field) {
            return get_class($field);
        });

        $candidatesLists = ExtrasPlugin::getInstance()->upgradeService->getMergeCandidates(false);

        return Craft::$app->getView()->renderTemplate('_extras/utilities/upgrade_inventory.twig', [
            'fieldTypes' => $fieldTypes->unique()->sort(),
            'mergeCandidatesLists' => $candidatesLists,
        ]);
    }
}
