<?php

namespace wsydney76\extras\web\assets\cpassets;

use Craft;
use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

/**
 * Cp Assets asset bundle
 */
class CustomCpAsset extends AssetBundle
{
    public $sourcePath = __DIR__ . '/dist';
    public $depends = [CpAsset::class];
    public $js = [];
    public $css = ['cp-assets.css'];
}
