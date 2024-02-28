<?php

namespace wsydney76\extras\behaviors;

use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\elements\Entry;
use craft\fields\Matrix;
use wsydney76\extras\records\TransferHistoryRecord;
use yii\base\Behavior;

class EntryBehavior extends Behavior
{
    public function getOwnerPath()
    {
        /** @var Entry $entry */
        $entry = $this->owner;

        $path = [];

        while ($entry = $entry->getOwner()) {
            array_unshift($path, $entry);
            if ($entry->section !== null) {
                break;
            }
        }

        return $path;
    }

    public function canTransfer()
    {
        /** @var Entry $entry */
        $entry = $this->owner;
        $user = Craft::$app->user->identity;

        if (!$entry->isProvisionalDraft) {
            return false;
        }

        if (!$user->can('transferprovisionaldrafts')) {
            return false;
        }

        if ($entry->creatorId == $user->id) {
            return false;
        }

        $hasOwnProvisionalDraft = Entry::find()
            ->draftOf($entry->getCanonical())
            ->drafts(true)
            ->provisionalDrafts(true)
            ->site('*')
            ->draftCreator($user)
            ->exists();
        if ($hasOwnProvisionalDraft) {
            return false;
        }

        return true;
    }

    public function getTransferHistory()
    {
        /** @var Entry $entry */

        $entry = $this->owner;
        if (!$entry->isProvisionalDraft) {
            return [];
        }

        if (!Craft::$app->db->tableExists(TransferHistoryRecord::tableName())) {
            return [];
        }

        return TransferHistoryRecord::find()
            ->where(['draftId' => $entry->draftId])
            ->orderBy('dateCreated desc')
            ->all();
    }

    public function isFieldModifiedForDiff(Field $field, ElementInterface $canonicalElement)
    {
        /** @var Entry $entry */
        /** @var MatrixBlock $block */

        $entry = $this->owner;
        if (!$field instanceof Matrix) {
            return $entry->isFieldModified($field->handle);
        }

        // Field changed detected for current site?
        if ($entry->isFieldModified($field->handle)) {
            return true;
        }


        $blocks = $entry->getFieldValue($field->handle)->anyStatus()->all();

        // Sub-Field changed?
        foreach ($blocks as $block) {
            foreach ($block->fieldLayout->getCustomFields() as $matrixField) {
                if ($block->isFieldModified($matrixField->handle)) {
                    return true;
                }
            }
        }

        $canonicalBlocks = $canonicalElement->getFieldValue($field->handle)->anyStatus()->all();

        // Block(s) added / deleted?
        if (count($blocks) != count($canonicalBlocks)) {
            return true;
        }

        $ids = array_map(function($b) {
            return $b->canonicalId;
        }, $blocks);
        $canonicalIds = array_map(function($b) {
            return $b->id;
        }, $canonicalBlocks);

        // Same count, but different order, or same count of blocks has been added/removed
        if (array_diff_assoc($ids, $canonicalIds)) {
            return true;
        }

        // Status changed
        foreach ($blocks as $block) {
            $canonicalBlock = $block->getCanonical();
            if ($block->status != $canonicalBlock->status) {
                return true;
            }
        }

        return $this->owner->isFieldModified($field->handle);
    }
}