<?php

namespace wsydney76\extras\models;

use Craft;
use craft\base\Model;

/**
 * Extras settings
 */
class Settings extends Model
{
    public bool $enableSidebarVisibility = false;
    public bool $enableElementmap = false;
    public bool $enableConditionRules= false;
    public bool $showAllSites = true;
    public bool $enableCpAssets = false;
    public string $customCss = '';
    public bool $enableOwnerPath = false;
    public bool $enableExtrasVariable = false;
    public bool $enableWidgets = false;
    public bool $enableDraftHelpers = false;

    public $diffRendererName = 'Combined';
    public $diffContext = 1;
    public $diffIgnoreCase = false;
    public $diffIgnoreWhitespace = true;
    public $diffDetailLevel = 'word';

    public $diffLineNumbers = true;
    public $diffSeparateBlock = true;
    public $diffShowHeader = false;

    public $diffMergeThreshold = 0.8;
}
