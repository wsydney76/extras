<?php

namespace wsydney76\extras\console\controllers;

use Craft;
use craft\base\MergeableFieldInterface;
use craft\ckeditor\Field;
use craft\console\Controller;
use craft\fieldlayoutelements\CustomField;
use craft\fields\Matrix;
use craft\helpers\Console;
use wsydney76\extras\ExtrasPlugin;
use yii\console\ExitCode;

class FieldsController extends Controller
{
    public function actionMergeCandidates($mergeablesOnly = 0): int
    {
        $mergeCandidatesLists = ExtrasPlugin::getInstance()->upgradeService->getMergeCandidates($mergeablesOnly);

        if (!$mergeCandidatesLists) {
            Console::output("No merge candidates found");
            return ExitCode::OK;
        }

        foreach ($mergeCandidatesLists as $candidatesList) {
            Console::output($candidatesList);
        }

        return ExitCode::OK;
    }

    public function actionReplaceField($entryTypeHandle = null, $fromHandle = null, $toHandle = null): int
    {

        if (!$entryTypeHandle || !$fromHandle || !$toHandle) {
            Console::output("Usage: craft _extras/fields/replace-field <entryTypeHandle> <fromFieldInstanceHandle> <toFieldHandle>");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $entryType = Craft::$app->entries->getEntryTypeByHandle($entryTypeHandle);
        if (!$entryType) {
            $this->stdout("Entry type $entryType not found\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $to = Craft::$app->fields->getFieldByHandle($toHandle);
        if (!$to) {
            $this->stdout("Field $toHandle not found\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $found = false;
        foreach ($entryType->getFieldLayout()->getAllElements() as $fieldInstance) {
            if ($fieldInstance instanceof CustomField) {

                // This is the original handle, or the overwritten one ???
                if ($fieldInstance->getField()->handle !== $fromHandle) {
                    continue;
                }

                if ($to::class !== $fieldInstance->getField()::class) {
                    $this->stdout("Field $fromHandle is not of the same type as $toHandle\n");
                    return ExitCode::UNSPECIFIED_ERROR;
                }

                 if ($to->handle === $fieldInstance->originalHandle) {
                     $this->stdout("Field $fromHandle is the same global field as $toHandle\n");
                     return ExitCode::UNSPECIFIED_ERROR;
                 }

                $found = true;

                 // $fieldInstance->setField($to);

                $label = Console::prompt("Label for $toHandle:", ['default' => $fieldInstance->label]);
                $handle = Console::prompt("Handle for $toHandle:", ['default' => $fieldInstance->handle]);
                $instructions = Console::prompt("Instructions for $toHandle:", ['default' => $fieldInstance->instructions]);


                $fieldInstance->label = (empty($label) || $label === $to->label) ? null : $label;
                $fieldInstance->handle = (empty($handle) || $handle === $to->handle) ? null : $handle;
                $fieldInstance->instructions = (empty($instructions) || $instructions === $to->instructions) ? null : $instructions;

                $fieldInstance->setFieldUid($to->uid);
            }
        }

        if (!$found) {
            $this->stdout("Field $fromHandle not found in entry type $entryTypeHandle\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        if (!Console::confirm("Replace $fromHandle with $toHandle in entry type $entryTypeHandle?")) {
            return ExitCode::UNSPECIFIED_ERROR;
        }

        if (!Craft::$app->entries->saveEntryType($entryType)) {
            $this->stdout("Could not save entry type $entryTypeHandle\n");
        }

        Console::output("Field $fromHandle replaced with $toHandle in entry type $entryTypeHandle");
        Console::output("Check entry type file, run craft project-config/apply.");

        return ExitCode::OK;
    }
}