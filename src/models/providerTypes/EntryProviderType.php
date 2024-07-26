<?php

namespace wsydney76\extras\models\providerTypes;

use Craft;

class EntryProviderType extends BaseProviderType
{

    public function getFields(string $providerHandles, string $fieldHandle): array
    {

        if ($providerHandles === '*' || !$providerHandles) {
            $entryTypeCandidates = Craft::$app->getEntries()->getAllEntryTypes();
        } else {
            $entryTypeCandidates = [];
            foreach (explode(',', $providerHandles) as $entryTypeHandle) {
                $entryType = Craft::$app->getEntries()->getEntryTypeByHandle($entryTypeHandle);
                if (!$entryType) {
                    throw new \InvalidArgumentException("Entry type not found: $entryTypeHandle");
                }
                $entryTypeCandidates[] = $entryType;
            }
        }

        return $this->getFieldsFromCandidates($entryTypeCandidates, $fieldHandle);
    }
}