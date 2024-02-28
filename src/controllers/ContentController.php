<?php

namespace wsydney76\extras\controllers;

use Craft;
use craft\db\Table;
use craft\elements\User;
use craft\helpers\Db;
use craft\web\Controller;
use wsydney76\extras\records\TransferHistoryRecord;
use yii\base\InvalidArgumentException;
use yii\db\Exception as DbExexption;
use yii\web\ForbiddenHttpException;

class ContentController extends Controller
{
    public function actionTransfer()
    {
        $session = Craft::$app->session;
        $request = Craft::$app->request;
        $security = Craft::$app->security;
        $user = Craft::$app->user->identity;

        if (!$user->can('transferprovisionaldrafts')) {
            throw new ForbiddenHttpException();
        }

        $draftId = $request->getRequiredBodyParam('draftId');
        $draftId = $security->validateData($draftId);
        if (!$draftId) {
            throw new InvalidArgumentException();
        }

        $creatorId = $request->getRequiredBodyParam('creatorId');
        $creatorId = $security->validateData($creatorId);
        if (!$creatorId) {
            throw new InvalidArgumentException();
        }

        try {
            $recordsUpdated = Db::update(Table::DRAFTS, [
                'creatorId' => $user->id
            ], [
                'id' => $draftId
            ], [], false);
        } catch (DbExexption $e) {
            $session->setError(Craft::t('_extras', 'Could not update drafts table') . ': ' . $e->getMessage());
            Craft::error('Could not update drafts table: ' . $e->getMessage(), 'work');
            return $this->redirectToPostedUrl();
        }

        if (!$recordsUpdated) {
            $session->setError(Craft::t('_extras', 'Could not update drafts table'));
            return $this->redirectToPostedUrl();
        }

        if (Craft::$app->db->tableExists(TransferHistoryRecord::tableName())) {
            $creator = User::findOne($creatorId);
            $transferHistoryRecord = new TransferHistoryRecord([
                'draftId' => $draftId,
                'fromUserId' => $creatorId,
                'toUserId' => $user->id,
                'fromUserName' => $creator ? $creator->fullName : $creatorId,
                'toUserName' => $user->fullName
            ]);
            $transferHistoryRecord->save();
        }

        $session->setNotice(Craft::t('_extras', 'Provisional draft transfered'));
        return $this->redirectToPostedUrl();
    }
}
