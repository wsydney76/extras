<?php
namespace wsydney76\extras\helpers;

use Craft;
use craft\base\ElementInterface;
use craft\helpers\Db;
class ElementHelper
{
    /**
     * Saves an element without updating its dateUpdated field.
     */
    public static function saveWithoutDateUpdated(
        ElementInterface $element,
        bool $runValidation = false,
        bool $propagate = true,
        bool $updateSearchIndex = false
    ): bool {
        $originalDateUpdated = $element->dateUpdated;
        if (!Craft::$app->getElements()->saveElement($element, $runValidation, $propagate, $updateSearchIndex)) {
            return false;
        }
        self::setDateUpdated($originalDateUpdated, $element);
        return true;
    }


    /**
     * @param \DateTime|null $originalDateUpdated
     * @param ElementInterface $element
     * @return void
     * @throws \yii\db\Exception
     */
    public static function setDateCreated(?\DateTime $dateCreated, ElementInterface $element): void
    {
        if ($dateCreated) {
            Craft::$app->getDb()->createCommand()
                ->update(
                    '{{%elements}}',
                    ['dateCreated' => Db::prepareDateForDb($dateCreated)],
                    ['id' => $element->id]
                )
                ->execute();
        }
    }

    /**
     * @param \DateTime|null $originalDateUpdated
     * @param ElementInterface $element
     * @return void
     * @throws \yii\db\Exception
     */
    public static function setDateUpdated(?\DateTime $originalDateUpdated, ElementInterface $element): void
    {
        if ($originalDateUpdated) {
            Craft::$app->getDb()->createCommand()
                ->update(
                    '{{%elements}}',
                    ['dateUpdated' => Db::prepareDateForDb($originalDateUpdated)],
                    ['id' => $element->id]
                )
                ->execute();
        }
    }
}
