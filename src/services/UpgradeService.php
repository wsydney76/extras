<?php

namespace wsydney76\extras\services;

use Craft;
use craft\base\MergeableFieldInterface;
use craft\fields\Matrix;
use craft\helpers\Console;
use yii\base\Component;

/**
 * Upgrade Service service
 */
class UpgradeService extends Component
{
    public function getMergeCandidates(mixed $mergeablesOnly)
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

        $candidatesLists = [];
        foreach ($signatures as $hash => $handles) {
            if (count($handles) > 1) {
                sort($handles);
                $candidatesLists[] = implode(', ', $handles);
            }
        }

        return $candidatesLists;
    }
}
