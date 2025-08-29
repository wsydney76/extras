<?php

namespace wsydney76\extras\controllers;

use Craft;
use craft\elements\Entry;
use craft\errors\ShellCommandException;
use craft\web\Controller;
use Throwable;
use wsydney76\extras\ExtrasPlugin;
use yii\db\Exception;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;

class DraftPackageController extends Controller
{


    protected array|bool|int $allowAnonymous = false;

    /**
     * @throws Exception
     * @throws \yii\base\Exception
     * @throws ForbiddenHttpException
     * @throws BadRequestHttpException
     * @throws ShellCommandException
     */
    public function actionApply()
    {
        $this->requireAdmin();

        $draftPackageId = Craft::$app->request->getRequiredBodyParam('draftPackageId');
        $afterSuccess = Craft::$app->request->getRequiredBodyParam('afterSuccess');
        $backupDb = Craft::$app->request->getBodyParam('backupDb');

        $package = Entry::findOne($draftPackageId);
        if (!$package) {
            return $this->asFailure('Draft package not found');
        }

        $draftPackageService = ExtrasPlugin::getInstance()->draftPackageService;
        $entries = $draftPackageService->getElementsForPackage($package);

        foreach ($entries as $entry) {
            if ($entry->hasErrors()) {
                return $this->asFailure(Craft::t('_extras', 'Draft package contains drafts that do not validate.'));
            }
        }

        $messages = $draftPackageService->applyDrafts($package, $entries, $afterSuccess, $backupDb );

        Craft::$app->session->setFlash('draftPackageMessages', $messages);
        return $this->asSuccess('Drafts applied successfully');
    }
}