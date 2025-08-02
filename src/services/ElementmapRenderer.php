<?php
/**
 * Element Map plugin for Craft 3.0
 *
 * @copyright Copyright Charlie Development
 */

namespace wsydney76\extras\services;

use Craft;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\commerce\elements\db\ProductQuery;
use craft\commerce\elements\db\VariantQuery;
use craft\commerce\elements\Product;
use craft\db\Query;
use craft\elements\Address;
use craft\elements\ContentBlock;
use craft\elements\db\AddressQuery;
use craft\elements\db\AssetQuery;
use craft\elements\db\CategoryQuery;
use craft\elements\db\ElementQuery;
use craft\elements\db\EntryQuery;
use craft\elements\db\GlobalSetQuery;
use craft\elements\db\TagQuery;
use craft\elements\db\UserQuery;
use craft\elements\Entry;
use craft\elements\User;
use Exception;
use putyourlightson\campaign\elements\CampaignElement;
use wsydney76\extras\events\ElementMapDataEvent;
use wsydney76\extras\ExtrasPlugin;
use wsydney76\extras\models\Settings;
use yii\base\Component;
use function version_compare;

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
        'craft\elements\Address' => 'getAddressElements',
        'craft\commerce\elements\Product' => 'getProductElements',
        'craft\commerce\elements\Variant' => 'getVariantElements',
        'putyourlightson\campaign\elements\CampaignElement' => 'getCampaignElements',
    ];

    const ELEMENT_TYPE_SORT_MAP = [
        'craft\elements\Entry' => '01',
        'craft\elements\GlobalSet' => '99',
        'craft\elements\Category' => '10',
        'craft\elements\Tag' => '15',
        'craft\elements\Asset' => '10',
        'craft\elements\User' => '20',
        'craft\elements\Address' => '21',
        'craft\commerce\elements\Product' => '30',
        'craft\commerce\elements\Variant' => '35',
        'putyourlightson\campaign\elements\CampaignElement' => '40',
    ];

    public const EVENT_ELEMENT_MAP_DATA = 'elementmap_data';


    private Settings $settings;
    private User $user;

    public function init(): void
    {
        // Should be present as the controller requires login
        $this->user = Craft::$app->getUser()->getIdentity();
        $this->settings = ExtrasPlugin::getInstance()->getSettings();
    }

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
            'incoming' => $this->getIncomingElements($element, $siteId),
            'outgoing' => $this->getOutgoingElements($element, $siteId),
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
        $sources = array_merge($sources, $this->getNestedEntryIds($sources));

        // Find all elements that have any of these elements as sources.
        $relationships = $this->getRelationships($sources, $siteId, false);

        // Outgoing connections may be going to elements such as variants.
        // Before retrieving proper elements and generating the map, their
        // appropriate owner elements should be found.
        // $relationships = $this->getUsableRelationElements($relationships, $siteId);

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

        // Also check for nested addresses
        $nestedIds = Address::find()->ownerId($elementId)->site('*')->ids();
        foreach ($nestedIds as $nestedId) {
            $ids[] = $nestedId;
            $ids = array_merge($ids, $this->getNestedEntryIds($nestedId));
        }

        if (version_compare(Craft::$app->getVersion(), '5.8.0', '>=')) {
            $nestedIds = ContentBlock::find()->ownerId($elementId)->site('*')->ids();
            foreach ($nestedIds as $nestedId) {
                $ids[] = $nestedId;
                $ids = array_merge($ids, $this->getNestedEntryIds($nestedId));
            }
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

        if (!$this->settings->showAllSites) {
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

        $elements = $query->all();

        // Replace content blocks with their owning elements.
        if (version_compare(Craft::$app->getVersion(), '5.8.0', '>=')) {
            foreach ($elements as $i => $element) {
                if ($element['type'] === 'craft\\elements\\ContentBlock') {
                    // get owning element for content blocks
                    $block = Craft::$app->getElements()->getElementById($element['id']);
                    $owner = $block->getOwner();
                    $elements[$i]['type'] = $owner::class;
                    $elements[$i]['id'] = $owner->id;
                }
            }
        }

        $elements = $this->groupByType($elements);

        $results = [];

        // This will iterate over available elements, bundled by type,
        // processing whole groups by type, adding them to the result

        // This has been simplified for Craft 5, there is no longer a need to handle MatrixBlock/SuperTable elements
        // TODO: Test if this works for all use cases

        while (count($elements)) {
            // Retrieve the next element type.
            reset($elements);
            $type = key($elements);

            foreach ($elements[$type] as $element) {
                $results[] = [
                    'id' => $element,
                    'type' => $type,
                ];
            }
            unset($elements[$type]);
        }

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
        // processing whole groups by type, adding them to the result

        // This has been simplified for Craft 5, there is no longer a need to handle MatrixBlock/SuperTable elements
        // TODO: Test if this works for all use cases

        while (count($elements)) {
            // Retrieve the next element type.
            reset($elements);
            $type = key($elements);

            foreach ($elements[$type] as $element) {
                $results[] = [
                    'id' => $element,
                    'type' => $type,
                ];
            }
            unset($elements[$type]);
        }
        return $results;
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
            } else {
                if ($this->hasEventHandlers(static::EVENT_ELEMENT_MAP_DATA)) {
                    $event = new ElementMapDataEvent([
                        'type' => $type,
                        'elements' => $elements[$type],
                        'siteId' => $siteId,
                    ]);
                    $this->trigger(static::EVENT_ELEMENT_MAP_DATA, $event);
                    if ($event->data) {
                        $results = array_merge($results, $event->data);
                    }
                }
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
    public function getIncomingElements(Element $element, int $siteId)
    {

        $targets = [$element->canonical->id ?? $element->id];

        // Assemble a set of elements that should be used as the targets.

        // Starting with the element itself.

        // Any variants within the element, as the variant and element share the
        // same editor pages (and can be referenced individually)
        $targets = array_merge($targets, $this->getVariantIdsByProducts($targets));

        // Find all elements that have any of these elements as targets.
        $relationships = $this->getRelationships($targets, $siteId, true);

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

        $elements = $this->getElementsForType(
            new EntryQuery('craft\\elements\\Entry'),
            $elementIds,
            $siteId);

        $results = [];

        $linkToNestedElement = $this->settings->linkToNestedElement;

        /** @var Entry $element */
        foreach ($elements as $element) {

            $title = $element->title;

            // TODO: Cleanup, this is a mess...
            $sectionName = 'n/a';

            $topLevelElement = $element;

            if ($element instanceof Entry) {
                if ($element->section) {
                    $sectionName = Craft::t('site', $element->section->name);
                } else {
                    $topLevelElement = $element->getRootOwner();

                    if ($topLevelElement) {
                        $title = $topLevelElement->title . ' -> ' . ($title ?: $this->getNestedElementText($element));
                        if ($topLevelElement instanceof Entry && $topLevelElement->section) {
                            $sectionName = Craft::t('site', $topLevelElement->section->name) . ' -> ' . Craft::t('site', $element->type->name);
                        } elseif ($topLevelElement instanceof Product) {
                            $sectionName = Craft::t('site', $topLevelElement->type->name);
                        } elseif ($topLevelElement instanceof CampaignElement) {
                            $sectionName = Craft::t('site', $topLevelElement->getCampaignType()->name);
                        } elseif ($topLevelElement instanceof User) {
                            $title = $topLevelElement->name . ' -> ' . $this->getNestedElementText($element);
                        }

                    } else {
                        $sectionName = ' -> ' . $element->type->name;
                    }
                }
            }


            $icon = $element instanceof Entry && $element->type->icon ?
                "@appicons/{$element->type->icon}.svg" :
                '@appicons/newspaper.svg';

            $color = $element instanceof Entry && $element->type->color ?
                $element->type->color->cssVar(500) :
                'var(--black)';

            $results[] = [
                'id' => $element->id,
                'icon' => $icon,
                'color' => $color,
                'title' => $title . $this->getExtraText($topLevelElement, $topLevelElement->type->name ?? 'n/a'),
                'url' => $linkToNestedElement ? $element->cpEditUrl : $topLevelElement->cpEditUrl,
                'sort' => self::ELEMENT_TYPE_SORT_MAP[get_class($element)] . $sectionName,
                'canView' => $element->canView($this->user)
            ];
        }

        return $results;
    }

    private function getAddressElements($elementIds, $siteId)
    {

        $elements = $this->getElementsForType(
            new AddressQuery('craft\elements\Address'),
            $elementIds,
            $siteId);
        $results = [];

        /** @var Entry $element */
        foreach ($elements as $element) {

            $title = $element->title;

            // TODO: Cleanup, this is a mess...
            $sectionName = 'n/a';

            $topLevelElement = $element->getRootOwner();

            /*if ($element instanceof Entry) {
                if ($element->section) {
                    $sectionName = Craft::t('site', $element->section->name);
                } else {
                    $topLevelElement = $element->getRootOwner();

                    if ($topLevelElement) {
                        $title = $topLevelElement->title . ' -> ' . ($title ?: $this->getNestedElementText($element));
                        if ($topLevelElement instanceof Entry && $topLevelElement->section) {
                            $sectionName = Craft::t('site', $topLevelElement->section->name) . ' -> ' . Craft::t('site', $element->type->name);
                        } elseif ($topLevelElement instanceof Product) {
                            $sectionName = Craft::t('site', $topLevelElement->type->name);
                        } elseif ($topLevelElement instanceof CampaignElement) {
                            $sectionName = Craft::t('site', $topLevelElement->getCampaignType()->name);
                        } elseif ($topLevelElement instanceof User) {
                            $title = $topLevelElement->name . ' -> ' . $this->getNestedElementText($element);
                        }

                    } else {
                        $sectionName = ' -> ' . $element->type->name;
                    }
                }
            }*/


            $icon =
                '@appicons/gear.svg';


            $results[] = [
                'id' => $element->id,
                'icon' => $icon,
                'color' => 'var(--black)',
                'title' => ($topLevelElement->title ?? $topLevelElement->name ?? $topLevelElement->id) . $this->getExtraText($topLevelElement,  'Address'),
                'url' => $topLevelElement->cpEditUrl,
                'sort' => self::ELEMENT_TYPE_SORT_MAP[get_class($element)] . $sectionName,
                'canView' => $element->canView($this->user)
            ];
        }

        return $results;
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
                'sort' => self::ELEMENT_TYPE_SORT_MAP[get_class($element)],
                'canView' => $element->canView($this->user)
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
        $elements = $this->getElementsForType(
            new CategoryQuery('craft\\elements\\Category'),
            $elementIds,
            $siteId);

        $results = [];
        foreach ($elements as $element) {
            $results[] = [
                'id' => $element->id,
                'icon' => '@vendor/craftcms/cms/src/icons/folder-open.svg',
                'title' => $element->title,
                'url' => $element->cpEditUrl,
                'sort' => self::ELEMENT_TYPE_SORT_MAP[get_class($element)],
                'canView' => $element->canView($this->user)
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
                'sort' => self::ELEMENT_TYPE_SORT_MAP[get_class($element)],
                'canView' => $element->canView($this->user)
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
                'image' => $element->kind === 'image' && $this->settings->showThumbnails ? $element->getUrl(['width' => 32, 'height' => 32]) : '',
                'title' => $element->title . ' (' . $volumeName . ')',
                'url' => $element->cpEditUrl,
                'fileUrl' => $element->url,
                'sort' => self::ELEMENT_TYPE_SORT_MAP[get_class($element)] . $volumeName,
                'canView' => $element->canView($this->user)
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
                'icon' => '@appicons/user.svg',
                'title' => $element->name,
                'url' => $element->cpEditUrl,
                'sort' => self::ELEMENT_TYPE_SORT_MAP[get_class($element)],
                'canView' => $element->canView($this->user)
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
        $elements = $this->getElementsForType(
            new ProductQuery('craft\\commerce\\elements\\Product'),
            $elementIds,
            $siteId);

        $results = [];
        foreach ($elements as $element) {
            $results[] = [
                'id' => $element->id,
                'icon' => '@vendor/craftcms/commerce/src/icon-mask.svg',
                'title' => $element->title . $this->getExtraText($element, $element->type->name),
                'url' => $element->cpEditUrl,
                'sort' => self::ELEMENT_TYPE_SORT_MAP[get_class($element)],
                'canView' => $element->canView($this->user)
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
            $product = $element->getOwner();
            $results[] = [
                'id' => $element->id,
                'icon' => '@vendor/craftcms/commerce/src/icon-mask.svg',
                'title' => $product->title . '-> ' . $element->title . ' (' . $product->type->name . ')',
                'url' => $product->cpEditUrl,
                'sort' => self::ELEMENT_TYPE_SORT_MAP[get_class($element)],
                'canView' => $element->canView($this->user)
            ];
        }
        return $results;
    }

    private function getCampaignElements($elementIds, $siteId)
    {
        $criteria = CampaignElement::find();
        $criteria->id = $elementIds;
        $criteria->site = '*';
        $elements = $criteria->all();

        $results = [];
        foreach ($elements as $element) {
            $results[] = [
                'id' => $element->id,
                'icon' => '@appicons/envelope.svg',
                'title' => $element->title . ' (' . $element->site->name . ', ' . $element->getCampaignType()->name . ')',
                'url' => $element->cpEditUrl,
                'sort' => self::ELEMENT_TYPE_SORT_MAP[get_class($element)],
                'canView' => $element->canView($this->user)
            ];
        }
        return $results;
    }

    /**
     * @param $elementIds
     * @param $siteId
     * @return array|\craft\base\ElementInterface[]
     */
    protected function getElementsForType($query, $elementIds, $siteId): array
    {
        $query->id = $elementIds;

        if ($this->settings->showAllSites) {
            $query->site('*');
            $query->unique();
            $query->preferSites([$siteId]);
        } else {
            $query->siteId = $siteId;
        }

        $query->provisionalDrafts(null);
        $query->drafts(null);
        $query->status(null);
        $query->revisions($this->settings->showRevisions ? null : false);
        $query->orderBy('title');
        return $query->all();
    }

    private function getExtraText($element, $type)
    {
        $parts = $type ? [$type] : [];

        if ($element->isProvisionalDraft) {
            $parts[] = Craft::t('_extras', 'Provisional Draft');
            $user = User::findOne($element->creatorId);
            if ($user) {
                $parts[] = $user->username;
            }
        } elseif ($element->getIsDraft()) {
            $parts[] = Craft::t('_extras', 'Draft');
            $parts[] = $element->draftName;
        } elseif ($element->getIsRevision()) {
            $parts[] = Craft::t('_extras', 'Revision') . ' ' . $element->revisionNum;
        }

        return ' (' . implode(', ', $parts) . ')';
    }

    /**
     * Return additonal text for nested elements, such as matrix/CKEditor field name/entry type.
     *
     * @param ElementInterface $element
     * @return string
     */
    private function getNestedElementText(ElementInterface $element)
    {
        $fieldName = '';

        try {
            $field = method_exists($element, 'getField') ? $element->getField() : null;
            if ($field && $field->name) {
                $fieldName = $field->name === '__blank__' ? '' : $field->name;
            }

            $text = $fieldName ? ($fieldName . '/' . $element->type->name) : $element->type->name;
        } catch (Exception $e) {
            // Tested for entries and variants, but may not work for all nested element types
            $text = 'n/a';
        }
        return $text;
    }
}
