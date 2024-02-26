<?php

namespace wsydney76\extras\elements\conditions;

use Craft;
use craft\base\conditions\BaseLightswitchConditionRule;
use craft\base\ElementInterface;
use craft\db\Table;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;
use craft\elements\Entry;

class HasDraftsConditionRule extends BaseLightswitchConditionRule implements ElementConditionRuleInterface
{
    /**
     * @inheritdoc
     */
    public function getLabel(): string
    {
        return Craft::t('app', 'My Drafts');
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
            $userId = Craft::$app->user->identity->id;
            $draftsTable = Table::DRAFTS;
            /* @phpstan-ignore-next-line */
            $query->andWhere("EXISTS (SELECT * from $draftsTable WHERE elements.id = $draftsTable.canonicalId AND $draftsTable.creatorId = $userId)");
        }
    }

    /**
     * @inheritdoc
     */
    public function matchElement(ElementInterface $element): bool
    {
        $user = Craft::$app->user->identity;
        /* @phpstan-ignore-next-line */
        return $this->value === false ? true : Entry::find()->draftOf($element->canonicalId)->draftCreator($user)->exists();
    }
}
