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
}
