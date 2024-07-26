<?php

namespace wsydney76\extras\models\providerTypes;

use Craft;

class AssetProviderType extends BaseProviderType
{

    public function getFields(string $providerHandles, string $fieldHandle): array
    {

        $volumeCandidates = [];
        if ($providerHandles === '*' || !$providerHandles) {
            $volumeCandidates = Craft::$app->getVolumes()->getAllVolumes();
        } else {
            foreach (explode(',', $providerHandles) as $volumeHandle) {
                $volume = Craft::$app->getVolumes()->getVolumeByHandle($volumeHandle);
                if (!$volume) {
                    throw new \InvalidArgumentException("Volume not found: $volumeHandle");
                }
                $volumeCandidates[] = $volume;
            }
        }

        return $this->getFieldsFromCandidates($volumeCandidates, $fieldHandle);
    }
}