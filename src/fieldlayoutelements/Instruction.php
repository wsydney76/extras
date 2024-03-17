<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace wsydney76\extras\fieldlayoutelements;

use Craft;
use craft\base\ElementInterface;
use craft\fieldlayoutelements\BaseUiElement;
use craft\helpers\Cp;
use craft\helpers\Html;
use yii\helpers\Markdown;

/**
 * Instruction represents an author instructions UI element that can be included in field layouts.
 */
class Instruction extends BaseUiElement
{

    /**
     * @var string The tip text
     */
    public string $instruction = '';


    /**
     * @inheritdoc
     */
    protected function selectorLabel(): string
    {
        if ($this->instruction) {
            return $this->instruction;
        }

        return Craft::t('_extras', 'Instruction');
    }

    /**
     * @inheritdoc
     */
    protected function selectorIcon(): ?string
    {
        return 'info';
    }

    /**
     * @inheritdoc
     */
    public function hasSettings()
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    protected function settingsHtml(): ?string
    {
        return
            Cp::textareaFieldHtml([
                'label' => Craft::t('_extras', 'Instruction'),
                'instructions' => Craft::t('app', 'Can contain Markdown formatting.'),
                'class' => ['nicetext'],
                'id' => 'instruction',
                'name' => 'instruction',
                'value' => $this->instruction,
            ]);
    }

    /**
     * @inheritdoc
     */
    public function formHtml(?ElementInterface $element = null, bool $static = false): ?string
    {

        $id = sprintf('instruction%s', mt_rand());
        $namespacedId = Craft::$app->getView()->namespaceInputId($id);

        $instruction = Markdown::process(Html::encode(Craft::t('_extras', $this->instruction)), 'pre-encoded');


        $js = null;


        $html = "<div id=\"$id\" class=\"\">" .
            "<blockquote class=\"note " . "\">" .
            $instruction .
            "</blockquote>" .
            '</div>';

        return $html;
    }

}
