<?php

namespace wsydney76\extras\console\controllers;

use Craft;
use craft\base\MergeableFieldInterface;
use craft\ckeditor\Field;
use craft\console\Controller;
use craft\fieldlayoutelements\CustomField;
use craft\fields\Matrix;
use craft\helpers\Console;
use yii\console\ExitCode;

class FieldsController extends Controller
{
    public function actionMergeCandidates($mergeablesOnly = 0): int
    {
        $signatures = [];

        foreach (Craft::$app->getFields()->getAllFields() as $field) {

            if ($mergeablesOnly && !$field instanceof MergeableFieldInterface) {
                continue;
            }

            // Skip Matrix and CKEditor fields
            if ($field instanceof Matrix || $field instanceof Field) {
                continue;
            }

            $signature = [
                'type' => $field::class,
                'translationMethod' => $field->translationMethod,
                'translationKeyFormat' => $field->translationKeyFormat,
                'searchable' => $field->searchable,
                // 'instructions' => $field->instructions,
                'settings' => $field->settings,
            ];

            $hash = md5(json_encode($signature));

            $signatures[$hash][] = $field->handle;
        }

        $found = false;
        foreach ($signatures as $hash => $handles) {
            if (count($handles) > 1) {
                $found = true;
                sort($handles);
                Console::output(implode(', ', $handles));
            }
        }

        if (!$found) {
            Console::output("No merge candidates found");
        }

        return ExitCode::OK;
    }

    public function actionReplaceField($entryTypeHandle = '', $fromHandle = '', $toHandle = ''): int
    {
        if (!$entryTypeHandle) {
            $entryTypeHandle = Console::prompt("Entry type handle:");
        }

        if (!$fromHandle) {
            $fromHandle = Console::prompt("From field instance handle:");
        }

        if (!$toHandle) {
            $toHandle = Console::prompt("To field handle:");
        }

        if (!$entryTypeHandle || !$fromHandle || !$toHandle) {
            $this->stdout("Missing parameters\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        /*if ($fromHandle === $toHandle) {
            $this->stdout("From and to fields are the same\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }*/

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

        if (!Console::confirm("Replace $fromHandle with $toHandle in entry type $entryTypeHandle?")) {
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $found = false;
        foreach ($entryType->getFieldLayout()->getAllElements() as $fieldInstance) {
            if ($fieldInstance instanceof CustomField) {

                if ($fieldInstance->handle && $fieldInstance->handle !== $fromHandle) {
                    continue;
                }

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

                return ExitCode::OK;

                $found = true;
                $fieldInstance->label = (Console::prompt("Label for $toHandle:", ['default' => $fieldInstance->label])) ?? null;
                $fieldInstance->handle = (Console::prompt("Handle for $toHandle:", ['default' => $fieldInstance->handle])) ?? null;
                $fieldInstance->instructions = (Console::prompt("Instructions for $toHandle:", ['default' => $fieldInstance->instructions])) ?? null;

                $fieldInstance->setFieldUid($to->uid);
            }
        }

        if (!$found) {
            $this->stdout("Field $fromHandle not found in entry type $entryTypeHandle\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        if (!Craft::$app->entries->saveEntryType($entryType)) {
            $this->stdout("Could not save entry type $entryTypeHandle\n");
        }

        Console::output("Field $fromHandle replaced with $toHandle in entry type $entryTypeHandle");
        Console::output("Check entry type file, run project-config/apply and clear caches");

        return ExitCode::OK;
    }
}