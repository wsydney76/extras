<?php

namespace wsydney76\extras\elements\conditions;

use Craft;
use craft\base\conditions\BaseLightswitchConditionRule;
use craft\base\ElementInterface;
use craft\db\Table;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;
use craft\elements\Entry;

class IsEditedConditionRule extends BaseLightswitchConditionRule implements ElementConditionRuleInterface
{
    /**
     * @inheritdoc
     */
    public function getLabel(): string
    {
        return Craft::t('_extras', 'Edited');
    }

    /**
     * @inheritdoc
     */
    public function getExclusiveQueryParams(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function modifyQuery(ElementQueryInterface $query): void
    {
        if ($this->value) {
            // $userId = Craft::$app->user->identity->id;
            $draftsTable = Table::DRAFTS;
            /* @phpstan-ignore-next-line */
            // $query->andWhere("EXISTS (SELECT * from $draftsTable WHERE elements.id = $draftsTable.canonicalId AND $draftsTable.creatorId = $userId and $draftsTable.provisional = 1)");
            $query->andWhere("EXISTS (SELECT * from $draftsTable WHERE elements.id = $draftsTable.canonicalId AND $draftsTable.provisional = 1)");
        }
    }

    /**
     * @inheritdoc
     */
    public function matchElement(ElementInterface $element): bool
    {
        // $user = Craft::$app->user->identity;
        /* @phpstan-ignore-next-line */
        // return $this->value === false ? true : Entry::find()->provisionalDrafts()->draftOf($element->canonicalId)->draftCreator($user)->exists();
        return $this->value === false ? true : Entry::find()->provisionalDrafts()->draftOf($element->canonicalId)->exists();
    }
}
