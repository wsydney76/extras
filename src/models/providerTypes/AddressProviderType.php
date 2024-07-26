<?php

namespace wsydney76\extras\models\providerTypes;

use Craft;
use craft\elements\Address;

class AddressProviderType extends BaseProviderType
{

    public function getFields(string $providerHandles, string $fieldHandle): array
    {

        $layout = Craft::$app->getFields()->getLayoutByType(Address::class);

        return [$this->getFieldFromLayout($layout, $fieldHandle)];
    }
}