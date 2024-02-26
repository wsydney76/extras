<?php

namespace wsydney76\extras\services;

use Craft;
use craft\base\Element;
use craft\base\Event;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\events\DefineAttributeHtmlEvent;
use craft\events\DefineHtmlEvent;
use craft\events\RegisterElementTableAttributesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\web\UrlManager;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use wsydney76\extras\ExtrasPlugin;
use yii\base\Exception;
use function count;

class Elementmap
{
    public function initElementMap(): void
    {

        // Set routes
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES, function(RegisterUrlRulesEvent $event) {
            $event->rules['elementmap-getrelations/<site:.*>/<class:.*>/<id:[\d]+>'] = '_extras/map/map';
        });

        // Render element maps within the appropriate template hooks.
//        Craft::$app->getView()->hook('cp.entries.edit.meta', [$this, 'renderEntryElementMap']);
//        Craft::$app->getView()->hook('cp.assets.edit.meta', [$this, 'renderAssetElementMap']);
//        Craft::$app->getView()->hook('cp.categories.edit.details', [$this, 'renderCategoryElementMap']);
//          Craft::$app->getView()->hook('cp.users.edit.meta', [$this, 'renderUserElementMap']);
//        Craft::$app->getView()->hook('cp.globals.edit.content', [$this, 'renderGlobalsElementMap']);
//        Craft::$app->getView()->hook('cp.commerce.product.edit.details', [$this, 'renderProductElementMap']);

        // Dont' show button in slideout editors
        if (!Craft::$app->request->isConsoleRequest && !Craft::$app->request->isAjax) {
            Event::on(
                Entry::class,
                Entry::EVENT_DEFINE_SIDEBAR_HTML,
                function(DefineHtmlEvent $event) {
                    $event->html .= $this->renderMap($event->sender, 'entry');;
                }
            );
//            Event::on(
//                Category::class,
//                Category::EVENT_DEFINE_SIDEBAR_HTML,
//                function(DefineHtmlEvent $event) {
//                    $event->html .= $this->renderMap($event->sender, 'category');;
//                }
//            );
            Event::on(
                Asset::class,
                Asset::EVENT_DEFINE_SIDEBAR_HTML,
                function(DefineHtmlEvent $event) {
                    $event->html .= $this->renderMap($event->sender, 'asset');;
                }
            );
        }

        // Allow some elements to have map data shown in their overview tables.
        Event::on(Asset::class, Element::EVENT_REGISTER_TABLE_ATTRIBUTES, [$this, 'registerElementmapTableAttributes']);
        Event::on(Asset::class, Element::EVENT_DEFINE_ATTRIBUTE_HTML, [$this, 'getElementmapTableAttributeHtml']);
//        Event::on(Category::class, Element::EVENT_REGISTER_TABLE_ATTRIBUTES, [$this, 'registerTableAttributes']);
//        Event::on(Category::class, Element::EVENT_DEFINE_ATTRIBUTE_HTML, [$this, 'getTableAttributeHtml']);
        Event::on(Entry::class, Element::EVENT_REGISTER_TABLE_ATTRIBUTES, [$this, 'registerElementmapTableAttributes']);
        Event::on(Entry::class, Element::EVENT_DEFINE_ATTRIBUTE_HTML, [$this, 'getElementmapTableAttributeHtml']);
//        Event::on(User::class, Element::EVENT_REGISTER_TABLE_ATTRIBUTES, [$this, 'registerTableAttributes']);
//        Event::on(User::class, Element::EVENT_DEFINE_ATTRIBUTE_HTML, [$this, 'getTableAttributeHtml']);
//        Event::on(Product::class, Element::EVENT_REGISTER_TABLE_ATTRIBUTES, [$this, 'registerTableAttributes']);
//        Event::on(Product::class, Element::EVENT_DEFINE_ATTRIBUTE_HTML, [$this, 'getTableAttributeHtml']);
    }



    /**
     * Handler for the Element::EVENT_REGISTER_TABLE_ATTRIBUTES event.
     */
    public function registerElementmapTableAttributes(RegisterElementTableAttributesEvent $event)
    {
        $event->tableAttributes['elementMap_incomingReferenceCount'] = ['label' => Craft::t('_extras', 'References From (Count)')];
        $event->tableAttributes['elementMap_outgoingReferenceCount'] = ['label' => Craft::t('_extras', 'References To (Count)')];
        $event->tableAttributes['elementMap_incomingReferences'] = ['label' => Craft::t('_extras', 'References From')];
        $event->tableAttributes['elementMap_outgoingReferences'] = ['label' => Craft::t('_extras', 'References To')];
    }

    /**
     * Handler for the Element::EVENT_DEFINE_ATTRIBUTE_HTML event.
     */
    public function getElementmapTableAttributeHtml(DefineAttributeHtmlEvent $event)
    {
        if ($event->attribute === 'elementMap_incomingReferenceCount') {
            $event->handled = true;
            $entry = $event->sender;
            $elements = ExtrasPlugin::getInstance()->renderer->getIncomingElements($entry, $entry->site->id);
            $event->html = Craft::$app->view->renderTemplate('_extras/_elementmap_indexcolumn', ['elements' => count($elements)]);
        } else if ($event->attribute === 'elementMap_outgoingReferenceCount') {
            $event->handled = true;
            $entry = $event->sender;
            $elements = ExtrasPlugin::getInstance()->renderer->getOutgoingElements($entry, $entry->site->id);
            $event->html = Craft::$app->view->renderTemplate('_extras/_elementmap_indexcolumn', ['elements' => count($elements)]);
        } else if ($event->attribute === 'elementMap_incomingReferences') {
            $event->handled = true;
            $entry = $event->sender;
            $elements = ExtrasPlugin::getInstance()->renderer->getIncomingElements($entry, $entry->site->id);
            $event->html = Craft::$app->view->renderTemplate('_extras/_elementmap_indexcolumn', ['elements' => $elements]);
        } else if ($event->attribute === 'elementMap_outgoingReferences') {
            $event->handled = true;
            $entry = $event->sender;
            $elements = ExtrasPlugin::getInstance()->renderer->getOutgoingElements($entry, $entry->site->id);
            $event->html = Craft::$app->view->renderTemplate('_extras/_elementmap_indexcolumn', ['elements' => $elements]);
        }
    }

    /**
     * Renders the element map for an entry within the entry editor, given the current Twig context.
     *
     * @param array $context The incoming Twig context.
     * @return string
     * @throws Exception
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function renderEntryElementMap(array &$context)
    {
        return $this->renderMap($context['entry'], 'entry');
    }

    /**
     * Renders an underlying incoming/outgoing element map.
     *
     * @param $element
     * @param $class
     * @return string
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws Exception
     */
    public function renderMap($element, $class)
    {
        return Craft::$app->view->renderTemplate('_extras/_elementmap', ['element' => $element, 'class' => $class]);
    }

    /**
     * Renders the element map for an entry within the entry editor, given the current Twig context.
     *
     * @param array $context The incoming Twig context.
     * @return string
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws Exception
     */
    public function renderAssetElementMap(array &$context)
    {
        return $this->renderMap($context['element'], 'asset');
    }

    /**
     * Renders the element map for a category within the category editor, given the current Twig context.
     *
     * @param array $context The incoming Twig context.
     * @return string
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws Exception
     */
//    public function renderCategoryElementMap(array &$context)
//    {
//        return $this->renderMap($context['category'], 'category');
//    }

    /**
     * Renders the element map for a user within the user editor, given the current Twig context.
     *
     * @param array $context The incoming Twig context.
     * @return string
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws Exception
     */
//    public function renderUserElementMap(array &$context)
//    {
//        return $this->renderMap($context['user'], 'user');
//    }

    /**
     * @param array $context
     * @return string
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws Exception
     */
//    public function renderGlobalsElementMap(array &$context)
//    {
//        return $this->renderMap($context['globalSet'], 'globalset');
//    }

    /**
     * Renders the element map for a product within the product editor, given the current Twig context.
     *
     * @param array $context The incoming Twig context.
     * @return string
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws Exception
     */
//    public function renderProductElementMap(array &$context)
//    {
//        return $this->renderMap($context['product'], 'product');
//    }
}