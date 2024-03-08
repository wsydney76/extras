<?php

namespace wsydney76\extras\elements\actions;

use Craft;
use craft\base\ElementAction;
use craft\base\ElementInterface;
use yii\base\Exception;

class CopyReferenceLinkTag extends ElementAction
{
    /**
     * @inheritdoc
     */
    public function getTriggerLabel(): string
    {
        return Craft::t('_extras', 'Copy reference tag with link');
    }

    /**
     * @inheritdoc
     */
    public function getTriggerHtml(): ?string
    {
        /** @var string|ElementInterface $elementType */
        /** @phpstan-var class-string<ElementInterface>|ElementInterface $elementType */
        $elementType = $this->elementType;

        if (($refHandle = $elementType::refHandle()) === null) {
            throw new Exception("Element type \"$elementType\" doesn't have a reference handle.");
        }

        Craft::$app->getView()->registerJsWithVars(fn($type, $refHandle) => <<<JS
(() => {
    new Craft.ElementActionTrigger({
        type: $type,
        bulk: false,
        activate: \$selectedItems => {
            Craft.ui.createCopyTextPrompt({
                label: Craft.t('app', 'Copy the reference tag'),
                value: '{' + $refHandle + ':' + \$selectedItems.find('.element').data('id') + ':link}',
            });
        },
    });
})();
JS, [static::class, $refHandle]);

        return null;
    }
}
