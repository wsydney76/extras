<?php
/**
 * Element Map plugin for Craft 3.0
 *
 * @copyright Copyright Charlie Development
 */

namespace wsydney76\extras\services;

use Craft;
use craft\commerce\elements\db\ProductQuery;
use craft\commerce\elements\db\VariantQuery;
use craft\commerce\elements\Product;
use craft\db\Query;
use craft\elements\db\AssetQuery;
use craft\elements\db\CategoryQuery;
use craft\elements\db\EntryQuery;
use craft\elements\db\GlobalSetQuery;
use craft\elements\db\TagQuery;
use craft\elements\db\UserQuery;
use craft\elements\Entry;
use craft\elements\User;
use wsydney76\extras\ExtrasPlugin;
use yii\base\Component;

class ElementmapRenderer extends Component
{
// Constants
    const ELEMENT_TYPE_MAP = [
        'craft\elements\Entry' => 'getEntryElements',
        'craft\elements\GlobalSet' => 'getGlobalSetElements',
        'craft\elements\Category' => 'getCategoryElements',
        'craft\elements\Tag' => 'getTagElements',
        'craft\elements\Asset' => 'getAssetElements',
        'craft\elements\User' => 'getUserElements',
        'craft\commerce\elements\Product' => 'getProductElements',
        'craft\commerce\elements\Variant' => 'getVariantElements',
    ];

    const ELEMENT_TYPE_SORT_MAP = [
        'craft\elements\Entry' => '01',
        'craft\elements\GlobalSet' => '99',
        'craft\elements\Category' => '10',
        'craft\elements\Tag' => '15',
        'craft\elements\Asset' => '10',
        'craft\elements\User' => '20',
        'craft\commerce\elements\Product' => '30',
        'craft\commerce\elements\Variant' => '35',
    ];

    /**
     * Generates a data structure containing elements that reference the given
     * element and those that the given element references.
     *
     * @param int $elementId The ID of the element to retrieve map
     * information about.
     * @param int $siteId The ID of the site context that information should
     * be gathered within.
     * @return array|null
     */
    public function getElementMap($element, int $siteId)
    {
        if (!$element) { // No element, no element map.
            return null;
        }

        return [
            'outgoing' => $this->getOutgoingElements($element, $siteId),
            'incoming' => $this->getIncomingElements($element, $siteId),
        ];
    }

    /**
     * Retrieves a list of elements that the given element references.
     *
     * @param int $elementId The ID of the element to retrieve map
     * information about.
     * @param int $siteId The ID of the site context that information should
     * be gathered within.
     */
    public function getOutgoingElements($element, int $siteId)
    {
        if (!$element) { // No element, no related elements.
            return null;
        }

        // Assemble a set of elements that should be used as the sources.

        // Starting with the element itself.
        $sources = [$element->id];

        // Any variants within the element, as the variant and element share the
        // same editor pages.
        if (Craft::$app->getPlugins()->isPluginEnabled('commerce')) {
            $sources = array_merge($sources, $this->getVariantIdsByProducts($sources));
        }

        // Craft 5: get IDs of all nested entries
        $sources = array_merge($sources, $this->getNestedEntryIds($element->id));

        // Any matrix blocks, because they contain fields that may reference
        // other elements
        // $sources = array_merge($sources, $this->getMatrixBlockIdsByOwners($sources));

        // Any super table blocks, for the same reason as matrix blocks, and
        // because they may themselves be contained within the matrix blocks.
        // $sources = array_merge($sources, $this->getSuperTableBlockIdsByOwners($sources));

        // Any matrix blocks, again, in the case of any matrix blocks being
        // contained within the super table blocks. This is thankfully as
        // far as the recursion can go.
        // $sources = array_merge($sources, $this->getMatrixBlockIdsByOwners($sources));

        // Find all elements that have any of these elements as sources.
        $relationships = $this->getRelationships($sources, $siteId, false);

        // Outgoing connections may be going to elements such as variants.
        // Before retrieving proper elements and generating the map, their
        // appropriate owner elements should be found.
        $relationships = $this->getUsableRelationElements($relationships, $siteId);

        // Retrieve the underlying elements from the relationships.
        return $this->getElementMapData($relationships, $siteId);
    }

    // Get ID's of all nested entries
    // TODO: Check if there is a native way to do this, improve performance
    private function getNestedEntryIds($elementId)
    {
        $ids = [];
        $nestedIds = Entry::find()->ownerId($elementId)->site('*')->ids();
        foreach ($nestedIds as $nestedId) {
            $ids[] = $nestedId;
            $ids = array_merge($ids, $this->getNestedEntryIds($nestedId));
        }

        return $ids;
    }

    /**
     * Attempts to retrieve variants for this element (used as a product), or
     * returns nothing if the element is not a product or if Craft Commerce is
     * not installed.
     *
     * @param $elementIds The element(s) to retrieve variants of.
     * @return array An array of element IDs.
     */
    private function getVariantIdsByProducts($elementIds)
    {
        // Make sure commerce is installed for this.
        if (!Craft::$app->getPlugins()->getPlugin('commerce')) {
            return [];
        }

        $conditions = [
            'primaryOwnerId' => $elementIds,
        ];
        return (new Query())
            ->select('id')
            ->from('{{%commerce_variants}}')
            ->where($conditions)
            ->column();
    }

    /**
     * Retrieves matrix blocks that are owned by the provided elements.
     *
     * @param $elementId The element(s) to retrieve blocks for.
     * @return array An array of elements, with their ID `id` and element type
     * `type`.
     */
    private function getMatrixBlockIdsByOwners($elementIds)
    {

        return Entry::find()
            ->ownerId($elementIds)
            ->status(null)
            ->site('*')
            ->ids();
    }

    /**
     * Retrieves super table blocks that are owned by the provided elements.
     *
     * @param $elementId The element(s) to retrieve blocks for.
     * @return array An array of elements, with their ID `id` and element type
     * `type`.
     */
    private function getSuperTableBlockIdsByOwners($elementIds)
    {
        // Make sure super table is installed.
        if (!Craft::$app->getPlugins()->getPlugin('super-table')) {
            return [];
        }

        $conditions = [
            'primaryOwnerId' => $elementIds,
        ];
        return (new Query())
            ->select('id')
            ->from('{{%supertableblocks}}')
            ->where($conditions)
            ->column();
    }

    /**
     * Retrieves elements that are either the source or target of relationships
     * with the provided elements.
     *
     * @param array $elementIds The array of elements to get relationships for.
     * @param int $siteId The site ID that relationships should exist within.
     * @param bool $getSources Set to true when the elementIds are for target
     * elements, and the sources are being searched for, or false when the
     * elementIds are for source elements, and the targets are being looked for.
     * @return array An array of arrays, the outer array being keyed by element
     * type, and the inner arrays containing element IDs.
     */
    private function getRelationships(array $elementIds, int $siteId, bool $getSources)
    {
        if ($getSources) {
            $fromcol = 'targetId';
            $tocol = 'sourceId';
        } else {
            $fromcol = 'sourceId';
            $tocol = 'targetId';
        }

        // Get a list of elements where the given element IDs are part of the relationship,
        // either target or source, defined by `getSources`.
        $conditions = [
            'and',
            [
                'in',
                $fromcol,
                $elementIds
            ],
        ];

        // TODO: Check

        if (!ExtrasPlugin::getInstance()->getSettings()->showAllSites) {
            if (!$getSources) {
                $conditions[] = [
                    'or',
                    ['sourceSiteId' => null],
                    ['sourceSiteId' => $siteId],
                ];
            }
        }

        $query = (new Query())
            ->select('[[e.id]] AS id, [[e.type]] AS type')
            ->from('{{%relations}} r')
            ->leftJoin('{{%elements}} e', '[[r.' . $tocol . ']] = [[e.id]]')
            ->where($conditions);

        $results = $query->all();

        $results = $this->groupByType($results);

        return $results;
    }

    /**
     * Sorts the elements provided into individual arrays, keyed by type.
     *
     * @param array $elements Tge elements to group.
     * @return array An array of arrays, the outer array being keyed by element
     * type, and the inner arrays containing element IDs.
     */
    private function groupByType(array $elements)
    {
        $results = [];
        foreach ($elements as $element) {
            if (!isset($results[$element['type']])) {
                $results[$element['type']] = [];
            }
            $results[$element['type']][] = $element['id'];
        }
        return $results;
    }

    /**
     * Finds elements within the relation set such as matrix blocks that should
     * instead reference their owning elements.
     *
     * @param array $elements The elements to find usable elements for.
     * @param int $siteId The site that the elements should be within.
     * @return array An array of elements, with their ID `id` and element type
     * `type`.
     */
    private function getUsableRelationElements(array $elements, int $siteId)
    {
        $results = [];

        // This will iterate over available elements, bundled by type,
        // processing whole groups by type, either adding them to the result
        // set if they can be used outright, or retrieving a related element
        // to use to show the relationship instead.
        while (count($elements)) {
            // Retrieve the next element type.
            reset($elements);
            $type = key($elements);

            // Determine if that element type should be processed or if it
            // should simply be added to the result set.
            switch ($type) {
                /* Just in case individual variant mapping turns out to be a bad idea.
                // Variants should instead map to their products, as those are
                // the elements through which they may be edited.*/
                case 'craft\\commerce\\elements\\Variant':
                    $items = $this->getProductsForVariants($elements[$type]);
                    unset($elements[$type]);
                    $items = $this->groupByType($items);
                    $elements = $this->mergeGroups($elements, $items);
                    break;

                // Matrix blocks should find their owners, and then those may
                // be reprocessed to determine if they are usable.
                case 'craft\\elements\\MatrixBlock':
                    $items = $this->getOwnersForMatrixBlocks($elements[$type]);
                    unset($elements[$type]);
                    $items = $this->groupByType($items);
                    $elements = $this->mergeGroups($elements, $items);
                    break;
                // Super table blocks should find their owners, and then those
                // may be reprocessed to determine if they are usable.
                case 'verbb\\supertable\\elements\\SuperTableBlockElement':
                    $items = $this->getOwnersForSuperTableBlocks($elements[$type]);
                    unset($elements[$type]);
                    $items = $this->groupByType($items);
                    $elements = $this->mergeGroups($elements, $items);
                    break;
                // Anything not processed above is alright to be added to the
                // result set and then retrieved later if it is supported.
                default:
                    foreach ($elements[$type] as $element) {
                        $results[] = [
                            'id' => $element,
                            'type' => $type,
                        ];
                    }
                    unset($elements[$type]);
                    break;
            }
        }
        return $results;
    }

    /**
     * Retrieves owner IDs/types of owning elements using the matrix block IDs
     * provided.
     *
     * @param $elementIds An array of IDs.
     * @return array An array of elements, with their ID `id` and element type
     * `type`.
     */
    private function getOwnersForMatrixBlocks($elementIds)
    {
        $conditions = [
            'mb.id' => $elementIds,
        ];
        return (new Query())
            ->select('[[e.id]] AS id, [[e.type]] AS type')
            ->from('{{%matrixblocks}} mb')
            ->leftJoin('{{%elements}} e', '[[mb.primaryOwnerId]] = [[e.id]]')
            ->where($conditions)
            ->all();
    }

    /**
     * Merges two groups in the same format as that provided by `groupByType`.
     *
     * @param array $groupsA The first group to merge.
     * @param array $groupsB The second group to merge.
     * @return array The two merged groups. An array of arrays, the outer array
     * being keyed by element type, and the inner arrays containing element IDs.
     */
    private function mergeGroups(array $groupsA, array $groupsB)
    {
        foreach ($groupsB as $type => $elements) {
            if (!isset($groupsA[$type])) {
                $groupsA[$type] = [];
            }
            $groupsA[$type] = array_merge($groupsA[$type], $elements);
        }
        return $groupsA;
    }

    /**
     * Retrieves owner IDs/types of owning elements using the super table block
     * IDs provided.
     *
     * @param $elements An array of IDs.
     * @return array An array of elements, with their ID `id` and element type
     * `type`.
     */
    private function getOwnersForSuperTableBlocks($elementIds)
    {
        $conditions = [
            'stb.id' => $elementIds,
        ];
        return (new Query())
            ->select('[[e.id]] AS id, [[e.type]] AS type')
            ->from('{{%supertableblocks}} stb')
            ->leftJoin('{{%elements}} e', '[[stb.primaryOwnerId]] = [[e.id]]')
            ->where($conditions)
            ->all();
    }

    /**
     * Converts a set of elements to an array of map-ready associative arrays.
     *
     * @param array $elements An array of elements (with `id` and `type`) to
     * retrieve map information for.
     * @param int $siteId The ID of the site to retrieve element data within.
     * @return array A set of elements that can be used to display the map.
     */
    private function getElementMapData(array $elements, int $siteId)
    {

        $elements = $this->groupByType($elements);
        $results = [];

        while (count($elements)) {
            // Retrieve the next element type.
            reset($elements);
            $type = key($elements);

            if (isset(self::ELEMENT_TYPE_MAP[$type])) {
                /** @noinspection SlowArrayOperationsInLoopInspection */
                $results = array_merge($results, call_user_func([$this, self::ELEMENT_TYPE_MAP[$type]], $elements[$type], $siteId));
            }

            unset($elements[$type]);
        }

        usort($results, function($a, $b) {
            return strcmp($a['sort'] . $a['title'], $b['sort'] . $b['title']);
        });

        return $results;
    }

    /**
     * Retrieves a list of elements referencing the given element.
     *
     * @param int $elementId The ID of the element to retrieve map
     * information about.
     * @param int $siteId The ID of the site context that information should
     * be gathered within.
     * @return array|null
     */
    public function getIncomingElements($element, int $siteId)
    {
        if (!$element) { // No element, no related elements.
            return null;
        }

        if ($element instanceof Entry) {
            $targets = Entry::find()->id($element->canonicalId)->status(null)->site('*')->ids();
        } else {
            $targets = [$element->id];
        }

        // Assemble a set of elements that should be used as the targets.

        // Starting with the element itself.

        // Any variants within the element, as the variant and element share the
        // same editor pages (and can be referenced individually)
        $targets = array_merge($targets, $this->getVariantIdsByProducts($targets));

        // Find all elements that have any of these elements as targets.
        $relationships = $this->getRelationships($targets, $siteId, true);

        // Incoming connections may be coming from elements such as matrix
        // blocks. Before retrieving proper elements and generating the map,
        // their appropriate owner elements should be found.
        $relationships = $this->getUsableRelationElements($relationships, $siteId);

        // Retrieve the underlying elements from the relationships.
        return $this->getElementMapData($relationships, $siteId);
    }

    /**
     * Retrieves product IDs/types of product elements using the variant IDs
     * provided.
     *
     * @param $elementIds An array of IDs.
     * @return array An array of elements, with their ID `id` and element type
     * `type`.
     */
    private function getProductsForVariants(array $elementIds)
    {
        $conditions = [
            'primaryOwnerId' => $elementIds,
        ];
        return (new Query())
            ->select('id')
            ->from('{{%commerce_variants}}')
            ->where($conditions)
            ->column();
    }

    /**
     * Retrieves entry elements based on a set of IDs.
     *
     * @param $elementIds The IDs of the entries to retrieve.
     * @param $siteId The ID of the site to use as the context for element data.
     */
    private function getEntryElements($elementIds, $siteId)
    {

        $criteria = new EntryQuery('craft\elements\Entry');
        $criteria->id = $elementIds;
        $criteria->site('*');
        $criteria->unique();
        $criteria->preferSites([$siteId]);
        $criteria->provisionalDrafts(null);
        $criteria->drafts(null);
        $criteria->status(null);
        $criteria->revisions(false);
        $elements = $criteria->all();

        $results = [];

        /** @var Entry $element */
        foreach ($elements as $element) {

            $title = $element->title;

            // TODO: Cleanup, this is a mess...
            $sectionName = 'n/a';

            if ($element instanceof Entry) {
                if ($element->section) {
                    $sectionName = Craft::t('site', $element->section->name);
                } else {
                    $topLevelEntry = $element->getRootOwner();

                    if ($topLevelEntry) {
                        if ($topLevelEntry instanceof Entry && $topLevelEntry->section) {
                            $sectionName = Craft::t('site', $topLevelEntry->section->name) . ' -> ' . Craft::t('site', $element->type->name);
                            if ($title) {
                                $title = $topLevelEntry->title . ' -> ' . $title;
                            } else {
                                $title = $topLevelEntry->title;
                            }
                        } elseif ($topLevelEntry instanceof Product) {
                            $sectionName = Craft::t('site', $topLevelEntry->type->name);
                            if ($title) {
                                $title = $topLevelEntry->title . ' -> ' . $title;
                            } else {
                                $title = $topLevelEntry->title;
                            }
                        }

                    }

                    else {
                        $sectionName = ' -> ' . $element->type->name;
                    }
                }
            }


            $text = $sectionName;
            if ($element->isProvisionalDraft) {
                $text .= ", " . Craft::t('_extras', 'Provisional Draft');
                $user = User::findOne($element->creatorId);
                if ($user) {
                    $text .= ", " . $user->username;
                }
            } elseif ($element->getIsDraft()) {
                $text .= ", " . Craft::t('_extras', 'Draft');
            } elseif ($element->getIsRevision()) {
                $text .= ", " . Craft::t('_extras', 'Revision');
            }

            $results[] = [
                'id' => $element->id,
                'icon' => '@vendor/craftcms/cms/src/icons/newspaper.svg',
                'title' => $title . ' (' . $text . ')',
                'url' => $element->cpEditUrl,
                'sort' => self::ELEMENT_TYPE_SORT_MAP[get_class($element)] . $sectionName
            ];
        }

        return $results;
    }

    // Get the top level entry (section)
    // TODO: Check if there is a native way to do this
    private function getTopLevelEntry(?Entry $entry, $level = 1)
    {
        if (!$entry) {
            return null;
        }

        if ($level > 4) {
            return null;
        }

        if ($entry->getIsRevision()) {
            return null;
        }

        return $entry->getRootOwner();

        $owner = $entry->owner;
        if (!$owner) {
            return null;
        }
        if ($owner && $owner->section) {
            return $owner;
        }

        if ($owner->owner) {
            return $owner->getRootOwner();
        }

        return null;
    }

    /**
     * Retrieves globalset elements based on a set of IDs.
     *
     * @param $elementIds The IDs of the globalsets to retrieve.
     * @param $siteId The ID of the site to use as the context for element data.
     */
    private function getGlobalSetElements($elementIds, $siteId)
    {
        $criteria = new GlobalSetQuery('craft\elements\GlobalSet');
        $criteria->id = $elementIds;
        $elements = $criteria->all();

        $results = [];
        foreach ($elements as $element) {
            $results[] = [
                'id' => $element->id,
                'icon' => '@vendor/craftcms/cms/src/icons/globe.svg',
                'title' => $element->name,
                'url' => $element->cpEditUrl,
                'sort' => self::ELEMENT_TYPE_SORT_MAP[get_class($element)]
            ];
        }
        return $results;
    }

    /**
     * Retrieves category elements based on a set of IDs.
     *
     * @param $elementIds The IDs of the categories to retrieve.
     * @param $siteId The ID of the site to use as the context for element data.
     */
    private function getCategoryElements($elementIds, $siteId)
    {
        $criteria = new CategoryQuery('craft\elements\Category');
        $criteria->id = $elementIds;
        $criteria->siteId = $siteId;
        $criteria->status = null;
        $elements = $criteria->all();

        $results = [];
        foreach ($elements as $element) {
            $results[] = [
                'id' => $element->id,
                'icon' => '@vendor/craftcms/cms/src/icons/folder-open.svg',
                'title' => $element->title,
                'url' => $element->cpEditUrl,
                'sort' => self::ELEMENT_TYPE_SORT_MAP[get_class($element)]
            ];
        }
        return $results;
    }

    /**
     * Retrieves tag elements based on a set of IDs.
     *
     * @param $elementIds The IDs of the tags to retrieve.
     * @param $siteId The ID of the site to use as the context for element data.
     */
    private function getTagElements($elementIds, $siteId)
    {
        $criteria = new TagQuery('craft\elements\Tag');
        $criteria->id = $elementIds;
        $criteria->siteId = $siteId;
        $elements = $criteria->all();

        $results = [];
        foreach ($elements as $element) {
            $results[] = [
                'id' => $element->id,
                'icon' => '@vendor/craftcms/cms/src/icons/tags.svg',
                'title' => $element->title,
                'url' => '/' . Craft::$app->getConfig()->getGeneral()->cpTrigger . '/settings/tags/' . $element->groupId,
                'sort' => self::ELEMENT_TYPE_SORT_MAP[get_class($element)]
            ];
        }
        return $results;
    }

    /**
     * Retrieves asset elements based on a set of IDs.
     *
     * @param $elementIds The IDs of the assets to retrieve.
     * @param $siteId The ID of the site to use as the context for element data.
     */
    private function getAssetElements($elementIds, $siteId)
    {
        $criteria = new AssetQuery('craft\elements\Asset');
        $criteria->id = $elementIds;
        $criteria->siteId = $siteId;
        $elements = $criteria->all();

        $imageBaseUrl = '/index.php?p=' . Craft::$app->config->general->cpTrigger . '/actions/assets/thumb&width=32&height=32&uid=';

        $results = [];
        foreach ($elements as $element) {
            $volumeName = $element->volume->name;
            $results[] = [
                'id' => $element->id,
                'kind' => $element->kind,
                'image' => $element->kind === 'image' ? $element->getUrl(['width' => 32, 'height' => 32]) : '',
                'title' => $element->title . ' (' . $volumeName . ')',
                'url' => $element->cpEditUrl,
                'fileUrl' => $element->url,
                'sort' => self::ELEMENT_TYPE_SORT_MAP[get_class($element)] . $volumeName
            ];
        }
        return $results;
    }

    /**
     * Retrieves user elements based on a set of IDs.
     *
     * @param $elementIds The IDs of the users to retrieve.
     * @param $siteId The ID of the site to use as the context for element data.
     */
    private function getUserElements($elementIds, $siteId)
    {
        $criteria = new UserQuery('craft\elements\User');
        $criteria->id = $elementIds;
        $elements = $criteria->all();

        $results = [];
        foreach ($elements as $element) {
            $results[] = [
                'id' => $element->id,
                'icon' => '@vendor/craftcms/cms/src/icons/user.svg',
                'title' => $element->name,
                'url' => $element->cpEditUrl,
                'sort' => self::ELEMENT_TYPE_SORT_MAP[get_class($element)]
            ];
        }
        return $results;
    }

    /**
     * Retrieves product elements based on a set of IDs.
     *
     * @param $elementIds The IDs of the products to retrieve.
     * @param $siteId The ID of the site to use as the context for element data.
     */
    private function getProductElements($elementIds, $siteId)
    {
        $criteria = new ProductQuery('craft\commerce\elements\Product');
        $criteria->id = $elementIds;
        $criteria->siteId = $siteId;
        $elements = $criteria->all();

        $results = [];
        foreach ($elements as $element) {
            $results[] = [
                'id' => $element->id,
                'icon' => '@vendor/craftcms/commerce/src/icon-mask.svg',
                'title' => $element->title,
                'url' => $element->cpEditUrl,
                'sort' => self::ELEMENT_TYPE_SORT_MAP[get_class($element)]
            ];
        }
        return $results;
    }

    /**
     * Retrieves variant elements based on a set of IDs.
     *
     * @param $elementIds The IDs of the variants to retrieve.
     * @param $siteId The ID of the site to use as the context for element data.
     */
    private function getVariantElements($elementIds, $siteId)
    {
        $criteria = new VariantQuery('craft\commerce\elements\Variant');
        $criteria->id = $elementIds;
        $criteria->siteId = $siteId;
        $elements = $criteria->all();

        $results = [];
        foreach ($elements as $element) {
            $results[] = [
                'id' => $element->id,
                'icon' => '@vendor/craftcms/commerce/src/icon-mask.svg',
                'title' => $element->product->title . ': ' . $element->title,
                'url' => $element->cpEditUrl,
                'sort' => self::ELEMENT_TYPE_SORT_MAP[get_class($element)]
            ];
        }
        return $results;
    }
}
