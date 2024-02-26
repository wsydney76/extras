<?php

namespace wsydney76\extras\web\assets\sidebarvisibility;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

/**
 * Sidebar Visibility asset bundle
 */
class SidebarVisibilityAsset extends AssetBundle
{
    public $sourcePath = __DIR__ . '/dist';
    public $depends = [CpAsset::class];
    public $js = ['sidebarvisibility.js'];
    public $css = ['sidebarvisibility.css'];
}
