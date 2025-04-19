<?php

namespace wsydney76\extras\services;

use Craft;
use craft\base\Element;
use craft\elements\Entry;
use wsydney76\extras\ExtrasPlugin;
use yii\base\Component;

/**
 * Draft Package Service service
 */
class DraftPackageService extends Component
{
    /**
     * @param mixed $package
     * @return array|\craft\base\ElementInterface[]
     */
    public function getElementsForPackage(mixed $package): array
    {
        $query = Entry::find()
            ->drafts()
            ->site('*')
            ->unique()
            ->status(null)
            ->relatedTo([
                'targetElement' => $package,
                'field' => 'draftPackage',
            ]);

        if (ExtrasPlugin::getInstance()->getSettings()->includeProvisionalDraftsInPackage) {
            $query->provisionalDrafts(null);
        }

        $entries = $query
            ->all();

        foreach ($entries as $entry) {
            $entry->scenario = Element::SCENARIO_LIVE;
            $entry->validate();
            foreach ($entries as $otherEntry) {
                if ($entry !== $otherEntry && $entry->canonicalId === $otherEntry->canonicalId) {
                    $entry->addError('canonicalId',
                        Craft::t(
                            '_extras',
                            'Duplicate canonical ID {id} found.',
                            ['id' => $entry->canonicalId]));
                    break;
                }
            }
        }
        return $entries;
    }
}
