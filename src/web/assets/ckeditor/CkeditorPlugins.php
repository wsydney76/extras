<?php

namespace wsydney76\extras\web\assets\ckeditor;

use craft\ckeditor\web\assets\BaseCkeditorPackageAsset;

class CkeditorPlugins extends BaseCkeditorPackageAsset
{
    public $sourcePath = __DIR__ . '/dist';
    public $depends = [];
    public $js = [
        'highlight.js',
        'mention.js'
    ];
    public $css = [];
    public array $pluginNames = [
        'Highlight',
        'Mention',

    ];
    public array $toolbarItems = [
        'highlight',
    ];
}