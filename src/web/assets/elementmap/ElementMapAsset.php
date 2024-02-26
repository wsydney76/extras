<?php
/**
 * Element Map plugin for Craft 3.0
 *
 * @copyright Copyright Charlie Development
 */

namespace wsydney76\extras\web\assets\elementmap;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class ElementMapAsset extends AssetBundle
{

    public $sourcePath = __DIR__ . '/dist';
    public $depends = [CpAsset::class];
    public $js = ['elementmap.js'];
    public $css = ['elementmap.css'];
}
