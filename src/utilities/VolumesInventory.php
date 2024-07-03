<?php

namespace wsydney76\extras\utilities;

use Craft;
use craft\base\Utility;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use yii\base\Exception;

/**
 * Volumes Inventory utility
 */
class VolumesInventory extends Utility
{
    public static function displayName(): string
    {
        return Craft::t('_extras', 'Volumes Inventory');
    }

    static function id(): string
    {
        return 'volumes-inventory';
    }

    public static function icon(): ?string
    {
        return 'warehouse';
    }

    /**
     * @throws SyntaxError
     * @throws Exception
     * @throws RuntimeError
     * @throws LoaderError
     */
    static function contentHtml(): string
    {
        return Craft::$app->getView()->renderTemplate('_extras/utilities/volumes_inventory.twig');
    }
}
