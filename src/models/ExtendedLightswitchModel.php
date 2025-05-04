<?php

namespace wsydney76\extras\models;

use Craft;
use craft\base\Model;

/**
 * Property model
 */
class ExtendedLightswitchModel extends Model
{
    public bool $selected = false;
    public string $comment = '';

    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            // ...
        ]);
    }

    public function __toString(): string
    {
        $string = Craft::t('_extras', $this->selected ? 'Yes' : 'No');

        if ($this->comment) {
            $string .= ' (' . Craft::t('site', $this->comment) . ')';
        }

        return $string;
    }
}
