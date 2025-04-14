<?php

namespace wsydney76\extras\controllers;

use Craft;
use craft\base\ElementInterface;
use craft\records\Element;
use craft\web\Controller;
use putyourlightson\campaign\elements\CampaignElement;
use wsydney76\extras\ExtrasPlugin;
use yii\web\NotFoundHttpException;

class ElementMapController extends Controller
{

    // Protected Properties
    // =========================================================================

    public function actionMap($siteId, $elementId)
    {
        $this->requireLogin();

        $element = Craft::$app->elements->getElementById($elementId, siteId: $siteId);

        if (!$element) {
            throw new NotFoundHttpException("Element not found: {$elementId}");
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
            if (!$elementRecord) {
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
