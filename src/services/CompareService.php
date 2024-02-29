<?php

namespace wsydney76\extras\services;

use Craft;
use craft\base\Component;
use Jfcherng\Diff\DiffHelper;
use Jfcherng\Diff\Renderer\RendererConstant;
use wsydney76\extras\ExtrasPlugin;
use function substr;

class CompareService extends Component
{
    public function calculateDiff($old, $new)
    {


        /** @var SettingsModel $settings */
        $settings = ExtrasPlugin::getInstance()->getSettings();

        // https://packagist.org/packages/jfcherng/php-diff

        // renderer class name:
        //     Text renderers: Context, JsonText, Unified
        //     HTML renderers: Combined, Inline, JsonHtml, SideBySide

        // the Diff class options
        $differOptions = [
            // show how many neighbor lines
            // Differ::CONTEXT_ALL can be used to show the whole file
            'context' => (int)$settings->diffContext,
            // ignore case difference
            'ignoreCase' => (bool)$settings->diffIgnoreCase,
            // ignore whitespace difference
            'ignoreWhitespace' => (bool)$settings->diffIgnoreWhitespace,
        ];

        // the renderer class options
        $rendererOptions = [
            // how detailed the rendered HTML in-line diff is? (none, line, word, char)
            'detailLevel' => $settings->diffDetailLevel,
            // renderer language: eng, cht, chs, jpn, ...
            // or an array which has the same keys with a language file
            'language' => substr(Craft::$app->language, 0, 2) === 'de' ? 'deu' : 'eng',
            // show line numbers in HTML renderers
            'lineNumbers' => (bool)$settings->diffLineNumbers,
            // show a separator between different diff hunks in HTML renderers
            'separateBlock' => (bool)$settings->diffSeparateBlock,
            // show the (table) header
            'showHeader' => (bool)$settings->diffShowHeader,
            // the frontend HTML could use CSS "white-space: pre;" to visualize consecutive whitespaces
            // but if you want to visualize them in the backend with "&nbsp;", you can set this to true
            'spacesToNbsp' => false,
            // HTML renderer tab width (negative = do not convert into spaces)
            'tabSize' => 4,
            // this option is currently only for the Combined renderer.
            // it determines whether a replace-type block should be merged or not
            // depending on the content changed ratio, which values between 0 and 1.
            'mergeThreshold' => (float)$settings->diffMergeThreshold,
            // this option is currently only for the Unified and the Context renderers.
            // RendererConstant::CLI_COLOR_AUTO = colorize the output if possible (default)
            // RendererConstant::CLI_COLOR_ENABLE = force to colorize the output
            // RendererConstant::CLI_COLOR_DISABLE = force not to colorize the output
            'cliColorization' => RendererConstant::CLI_COLOR_ENABLE,
            // this option is currently only for the Json renderer.
            // internally, ops (tags) are all int type but this is not good for human reading.
            // set this to "true" to convert them into string form before outputting.
            'outputTagAsString' => false,
            // this option is currently only for the Json renderer.
            // it controls how the output JSON is formatted.
            // see available options on https://www.php.net/manual/en/function.json-encode.php
            'jsonEncodeFlags' => \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE,
            // this option is currently effective when the "detailLevel" is "word"
            // characters listed in this array can be used to make diff segments into a whole
            // for example, making "<del>good</del>-<del>looking</del>" into "<del>good-looking</del>"
            // this should bring better readability but set this to empty array if you do not want it
            'wordGlues' => [' ', '-'],
            // change this value to a string as the returned diff if the two input strings are identical
            'resultForIdenticals' => null,
            // extra HTML classes added to the DOM of the diff container
            'wrapperClasses' => ['diff-wrapper'],
        ];

        return DiffHelper::calculate($old, $new, $settings->diffRendererName, $differOptions, $rendererOptions);
    }
}
