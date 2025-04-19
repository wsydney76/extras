<?php

namespace wsydney76\extras\controllers;

use Craft;
use craft\elements\Entry;
use craft\web\Controller;
use Throwable;
use wsydney76\extras\ExtrasPlugin;

class DraftPackageController extends Controller
{
    protected array|bool|int $allowAnonymous = false;

    public function actionApply()
    {
        $this->requireAdmin();

        $draftPackageId = Craft::$app->request->getRequiredBodyParam('draftPackageId');
        $afterSuccess = Craft::$app->request->getRequiredBodyParam('afterSuccess');
        $settings = ExtrasPlugin::getInstance()->getSettings();

        $package = Entry::findOne($draftPackageId);
        if (!$package) {
            return $this->asFailure('Draft package not found');
        }

        $entries = ExtrasPlugin::getInstance()->draftPackageService->getElementsForPackage($package);

        foreach ($entries as $entry) {
            if ($entry->hasErrors()) {
               return $this->asFailure(Craft::t('_extras', 'Draft package contains drafts that do not validate.'));
            }
        }

        $messages = [];

        if (Craft::$app->request->getBodyParam('backupDb')) {
            $filePath = Craft::$app->db->backup();
            $messages[] = Craft::t('_extras', 'Database backup created at {filePath}', [
                'filePath' => $filePath,
            ]);
        }

        $transaction = Craft::$app->getDb()->beginTransaction();
        foreach ($entries as $i => $entry) {

            try {

                if ($afterSuccess === 'detach') {
                    $entry->setFieldValue($settings->draftPackageField, []);
                    Craft::$app->elements->saveElement($entry);
                }
                $updatedElement = Craft::$app->drafts->applyDraft($entry, [
                    'updateSearchIndexForOwner' => true,
                ]);
                $messages[] = Craft::t('_extras', 'Draft {title} applied successfully', [
                    'title' => $updatedElement->title,
                ]);
            } catch (Throwable $e) {
                $transaction->rollBack();
                Craft::$app->session->setFlash('draftPackageMessages',  [$entry->id . ' '. $e->getMessage()]);
                return $this->asFailure('Drafts applied with errors');
            }
        }

        $transaction->commit();

        if ($afterSuccess === 'delete') {
            Craft::$app->elements->deleteElement($package);
            $messages[] = Craft::t('_extras', 'Draft package deleted');
        }

        Craft::$app->session->setFlash('draftPackageMessages', $messages);
        return $this->asSuccess('Drafts applied successfully');
    }
}