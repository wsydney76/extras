<?php

namespace wsydney76\extras\behaviors;

use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\db\Query;
use craft\db\Table;
use craft\elements\Entry;
use craft\fields\Matrix;
use Illuminate\Support\Collection;
use wsydney76\extras\records\TransferHistoryRecord;
use yii\base\Behavior;

class EntryBehavior extends Behavior
{
    public function getOwnerPath(bool $fullPath = false): Collection
    {

        // restrict to top level entry
        if (!$fullPath) {
            return Collection::make([$this->owner->getRootOwner()]);
        }

        /** @var Entry $element */
        $element = $this->owner;

        $path = Collection::make();

        while ($element = $element->getOwner()) {
            $path = $path->prepend($element);
            // array_unshift($path, $entry);
            if (($element instanceof Entry && $element->section) || $element instanceof Product || $element instanceof Variant) {
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

    public function getSectionPermissionKey(string $baseKey) :string
    {
        /** @var Entry $entry */
        $entry = $this->owner;

        if ($entry->section) {
               return $baseKey . ':' . $entry->section->uid;
        }

        return $baseKey . ':' . $this->getOwnerPath()->first()->section->uid;
    }

    public function getRawContent()
    {
        /** @var Entry $entry */
        $entry = $this->owner;
        return (new Query())
            ->from(Table::ELEMENTS_SITES)
            ->select('content')
            ->where(['elementId' => $entry->id, 'siteId' => $entry->siteId])
            ->scalar();
    }
}