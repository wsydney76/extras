<?php

namespace wsydney76\extras\models;

use craft\base\Model;

/**
 * Extras settings
 */
class Settings extends Model
{
    public bool $enableSidebarVisibility = false;
    public bool $enableElementmap = false;
    public bool $enableConditionRules = false;
    public bool $showAllSites = true;
    public bool $linkToNestedElement = false;
    public bool $enableCpAssets = false;
    public string $bodyFontSize = '';
    public string $customCss = '';
    public bool $enableOwnerPath = false;
    public bool $enableExtrasVariable = false;
    public bool $enableWidgets = false;
    public bool $enableDraftHelpers = false;
    public bool $enableCollectionMakros = false;
    public bool $enableElementActions = false;
    public bool $enableFieldLayoutElements = false;

    public bool $enableTwigExtension = false;
    public bool $enableRestoreDismissedTips = false;

    public bool $enableVolumeInventory = false;
    public bool $enableUpgradeInventory = false;

    public bool $enableActionRoutes = false;

    public bool $enableViewLinkInCards = false;

    // never, devMode, always
    public string $enableInspectPreviewTarget = 'no';

    public string $diffRendererName = 'Combined';
    public int $diffContext = 1;
    public bool $diffIgnoreCase = false;
    public bool $diffIgnoreWhitespace = true;
    public string $diffDetailLevel = 'word';

    public bool $diffLineNumbers = true;
    public bool $diffSeparateBlock = true;
    public bool $diffShowHeader = false;

    public float $diffMergeThreshold = 0.8;
}
