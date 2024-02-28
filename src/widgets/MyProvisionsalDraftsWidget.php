<?php

namespace wsydney76\extras\widgets;

use Craft;
use craft\base\Widget;
use craft\elements\Entry;
use craft\helpers\Cp;
use craft\helpers\Html;

class MyProvisionsalDraftsWidget extends Widget
{
    public static function displayName(): string
    {
        return Craft::t('_extras', 'My Open Edits');
    }

    public static function icon(): ?string
    {
        return Craft::getAlias('@appicons/draft.svg');
    }

    protected static function allowMultipleInstances(): bool
    {
        return false;
    }

    public function getBodyHtml(): ?string
    {
        $drafts = Entry::find()
            ->drafts(true)
            ->provisionalDrafts(true)
            ->draftCreator(Craft::$app->user->identity)
            ->site('*')
            ->unique()
            ->status(null)
            ->orderBy('dateUpdated desc')
            ->all();

        if (empty($drafts)) {
            return Html::tag('div', Craft::t('app', 'You don’t have any open edits.'), [
                'class' => ['zilch', 'small'],
            ]);
        }

        $html = Html::beginTag('ul', [
            'class' => 'widget__list chips',
            'role' => 'list',
        ]);

        foreach ($drafts as $draft) {
            $html .= Html::tag('li', Cp::elementChipHtml($draft), [
                'class' => 'widget__list-item',
            ]);
        }

        $html .= Html::endTag('ul');

        return $html;
    }
}
