<?php

namespace wsydney76\extras\controllers;

use Craft;
use craft\web\Controller;

class ViewController extends Controller
{
    protected array|bool|int $allowAnonymous = true;

    public function actionRenderTemplate()
    {
        $template = $this->request->getRequiredBodyParam('template');
        $template = Craft::$app->security->validateData($template);
        if (!$template) {
            return $this->asFailure('Template has been tampered with');
        }
        $variables = $this->request->getBodyParam('variables', []);
        $templateMode = $this->request->getBodyParam('templateMode', Craft::$app->getView()->getTemplateMode());

        return $this->asSuccess('Templated rendered', [
            'html' => Craft::$app->getView()->renderTemplate($template, $variables, $templateMode)
        ]);
    }
}