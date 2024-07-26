<?php

namespace wsydney76\extras\events;

use craft\base\Event;
use wsydney76\extras\models\providerTypes\BaseProviderType;

class RegisterProviderTypeHandlerEvent extends Event
{
    public string $providerType;
    public ?BaseProviderType $providerTypeHandler = null;

}