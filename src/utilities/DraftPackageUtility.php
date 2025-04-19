<?php

namespace wsydney76\extras\utilities;

use Craft;
use craft\base\Element;
use craft\base\Utility;
use craft\elements\Entry;
use wsydney76\extras\ExtrasPlugin;

/**
 * Draft Package Utility utility
 */
class DraftPackageUtility extends Utility
{
    public static function displayName(): string
    {
        return Craft::t('_extras', 'Draft Packages');
    }

    static function id(): string
    {
        return 'draft-package-utility';
    }

    public static function icon(): ?string
    {
        return 'cubes';
    }

    static function contentHtml(): string
    {
        $sectionSlug = ExtrasPlugin::getInstance()->getSettings()->draftPackageSection;

        $packages = Entry::find()
            ->section($sectionSlug)
            ->orderBy('title')
            ->all();

        $draftsByPackage = [];
        foreach ($packages as $package) {
            $draftsByPackage[$package->id] = ExtrasPlugin::getInstance()
                ->draftPackageService
                ->getElementsForPackage($package);
        }

        return Craft::$app->getView()->renderTemplate('_extras/utilities/draft_package.twig', [
            'draftPackages' => $packages,
            'draftsByPackage' => $draftsByPackage,
        ]);
    }


}
