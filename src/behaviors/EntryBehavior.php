<?php

namespace wsydney76\extras\behaviors;

use craft\elements\Entry;
use yii\base\Behavior;

class EntryBehavior extends Behavior
{
    public function getOwnerPath()
    {
        /** @var Entry $entry */
        $entry = $this->owner;

        $path = [];

        while ($entry = $entry->getOwner()) {
            array_unshift($path, $entry);
            if ($entry->section !== null) {
                break;
            }
        }

        return $path;
    }
}