<?php

namespace wsydney76\extras\controllers;

use Craft;
use craft\web\Controller;
use wsydney76\extras\ExtrasPlugin;

class SettingsController extends Controller
{
    public function actionEdit()
    {
        return Craft::$app->view->renderPageTemplate('_extras/_settings.twig', [
            'settings' => ExtrasPlugin::getInstance()->getSettings(),
            'config' => Craft::$app->getConfig()->getConfigFromFile('_extras')
        ]);
    }

    public function actionSave()
    {
        $this->requirePostRequest();
        $postedSettings = Craft::$app->getRequest()->getRequiredBodyParam('settings');

        $settings = ExtrasPlugin::getInstance()->settings;
        $settings->setAttributes($postedSettings, false);

        // Save it
        Craft::$app->getPlugins()->savePluginSettings(ExtrasPlugin::getInstance(), $settings->getAttributes());

        $notice = Craft::t('_extras', 'Plugin settings saved.');


        Craft::$app->getSession()->setSuccess($notice);

        return $this->redirectToPostedUrl();
    }
}