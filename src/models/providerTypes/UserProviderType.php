<?php

namespace wsydney76\extras\models\providerTypes;

use Craft;
use craft\elements\User;

class UserProviderType extends BaseProviderType
{

    public function getFields(string $providerHandles, string $fieldHandle): array
    {

        $layout = Craft::$app->getFields()->getLayoutByType(User::class);

        return [$this->getFieldFromLayout($layout, $fieldHandle)];
    }
}