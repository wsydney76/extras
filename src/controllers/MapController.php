<?php

namespace wsydney76\extras\controllers;

use Craft;
use craft\base\ElementInterface;
use craft\commerce\elements\Product;
use craft\db\Query;
use craft\db\Table;
use craft\elements\Asset;
use craft\elements\Category;
use craft\elements\Entry;
use craft\elements\GlobalSet;
use craft\elements\Tag;
use craft\elements\User;
use craft\gql\arguments\mutations\Draft;
use craft\records\Element;
use craft\web\Controller;
use putyourlightson\campaign\elements\CampaignElement;
use wsydney76\extras\ExtrasPlugin;
use yii\web\NotFoundHttpException;

class MapController extends Controller
{

    // Protected Properties
    // =========================================================================

    public function actionMap($site, $class, $id)
    {


        // TODO: ensure that drafts/sites are handled correctly for all element types
        switch ($class) {
            case 'entry':
            {
                $element = $this->getElement(Entry::find(), $id, $site);
                break;
            }
            case 'asset':
            {
                $element = Asset::find()->site($site)->id($id)->one();
                break;
            }

            case 'category':
            {
                $element = $this->getElement(Category::find(), $id, $site);
                break;
            }

            case 'tag':
            {
                $element = Tag::find()->id($id)->one();
                break;
            }

            case 'user':
            {
                $element = User::find()->id($id)->one();
                break;
            }

            case 'globalset':
            {
                $element = GlobalSet::find()->id($id)->one();
                break;
            }

            case 'product':
            {
                $element = $this->getElement(Product::find(), $id, $site);

                break;
            }

            case 'campaign':
            {
                $element = CampaignElement::find()->id($id)->one();
                break;
            }

            default:
                $siteId = Craft::$app->sites->getSiteByHandle($site)->id;
                $element = Craft::$app->elements->getElementById($id, siteId: $siteId);
        }

        if (!$element) {
            throw  new NotFoundHttpException("Element not found: {$class}/{$id}");
        }

        $plugin = ExtrasPlugin::getInstance();
        $map = $plugin->renderer->getElementMap($element, $element->siteId);


        return Craft::$app->view->renderTemplate('_extras/_elementmap_content', [
            'element' => $element,
            'map' => $map
        ]);
    }

    // Public Methods
    // =========================================================================
    /**
     * @param $query
     * @param $id
     * @param $site
     * @return array|ElementInterface|mixed|null
     */
    protected function getElement($query, $id, $site): mixed
    {
        $draftId = Craft::$app->request->getParam('draftId');
        $siteId = Craft::$app->sites->getSiteByHandle($site)->id;

        if ($draftId) {
            $elementRecord = Element::findOne(['draftId' => $draftId]);
            if(!$elementRecord) {
                throw new NotFoundHttpException("Draft not found: {$draftId}");
            }
            return Craft::$app->elements->getElementById($elementRecord->id, $elementRecord->type, $siteId);
        }

        return Craft::$app->elements->getElementById($id, siteId: $siteId);
    }

    /**
     * @var    bool|array Allows anonymous access to this controller's actions.
     *         The actions must be in 'kebab-case'
     * @access protected
     */
    protected array|bool|int $allowAnonymous = [];
}
