<?php

namespace wsydney76\extras\controllers;

use Craft;
use craft\commerce\elements\Product;
use craft\elements\Asset;
use craft\elements\Category;
use craft\elements\Entry;
use craft\elements\GlobalSet;
use craft\elements\Tag;
use craft\elements\User;
use craft\web\Controller;
use wsydney76\extras\ExtrasPlugin;
use yii\web\NotFoundHttpException;

class MapController extends Controller
{

    // Protected Properties
    // =========================================================================

    public function actionMap($site, $class, $id)
    {

        $element = null;
        switch ($class) {
            case 'entry':
            {
                $draftId = Craft::$app->request->getParam('draftId');

                if ($draftId) {
                    $element = Entry::find()->draftId($draftId)->provisionalDrafts(null)->status(null)->site('*')->preferSites([$site])->unique()->one();
                } else {
                    $element = Entry::find()->id($id)->status(null)->site('*')->preferSites([$site])->unique()->one();
                    if (!$element) {
                        $element = Entry::find()->drafts(true)->provisionalDrafts(null)->id($id)->status(null)->site('*')->preferSites([$site])->unique()->one();
                    }
                    if (!$element) {
                        $element = Entry::find()->revisions(true)->id($id)->status(null)->site('*')->preferSites([$site])->unique()->one();
                    }
                }

                break;
            }
            case 'asset':
            {
                $element = Asset::find()->site($site)->id($id)->one();
                break;
            }

            case 'category':
            {
                $element = Category::find()->id($id)->one();
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
                $element = Product::find()->id($id)->one();
                break;
            }
        }

        if (!$element) {
            throw  new NotFoundHttpException("Element not found: {$class}/{$id}");
        }

        $plugin = ExtrasPlugin::getInstance();
        $map = $plugin->renderer->getElementMap($element, $element->siteId);


        return Craft::$app->view->renderTemplate('_extras/_elementmap_content', ['map' => $map]);;
    }

    // Public Methods
    // =========================================================================
    /**
     * @var    bool|array Allows anonymous access to this controller's actions.
     *         The actions must be in 'kebab-case'
     * @access protected
     */
    protected array|bool|int $allowAnonymous = [];
}
