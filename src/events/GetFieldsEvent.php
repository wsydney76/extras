<?php

namespace wsydney76\extras\events;

use yii\base\Event;

class GetFieldsEvent extends Event
{
    public array $fields = [];

    public string $providerType;
    public string $providerHandles;
    public string $fieldHandle;
}