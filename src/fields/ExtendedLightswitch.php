<?php

namespace wsydney76\extras\fields;

use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\base\InlineEditableFieldInterface;
use craft\helpers\Cp;
use craft\helpers\Db;
use wsydney76\extras\models\ExtendedLightswitchModel;
use yii\db\ExpressionInterface;
use yii\db\Schema;
use function array_merge;
use function is_array;

/**
 * Extended lightswitch field type
 */
class ExtendedLightswitch extends Field implements InlineEditableFieldInterface
{
    public static function displayName(): string
    {
        return Craft::t('_extras', 'Extended Lightswitch');
    }

    /**
     * @inheritDoc
     */
    /**
     * @inheritDoc
     */
    public static function dbType(): array|string
    {
        return [
            'selected' => Schema::TYPE_BOOLEAN,
            'comment' => Schema::TYPE_STRING,
        ];
    }

    /**
     * @inheritDoc
     */
    public static function phpType(): string
    {
        return ExtendedLightswitchModel::class;
    }

    /**
     * @inheritDoc
     */
    public static function icon(): string
    {
        return 'toggle-on';
    }

    /**
     * @inheritDoc
     */
    public static function queryCondition(array $instances, mixed $value, array &$params): ExpressionInterface|false|array|string|null
    {

        $valueSql = static::valueSql($instances, 'selected');

        if ($valueSql === null) {
            return false;
        }

        if ($value === true) {
            $value = 'true';
        }

         // \Craft::dd(Db::parseParam($valueSql, $value, true, Schema::TYPE_JSON));
        return Db::parseParam($valueSql, $value);

        // $value = $value ? 'true' : 'false';
        // $uid = $instances[0]->layoutElement->uid;
        // return "cast(JSON_EXTRACT(content, '$.\"{$uid}\".selected') AS CHAR(5)) = '$value'";
        // return "{$instances[0]->getValueSql('selected')} = '$value'";

    }

    /**
     * @inheritDoc
     */
    public function getPreviewHtml(mixed $value, ElementInterface $element): string
    {

        if ($value->selected) {
            return "<span class=\"status-label green\"><span class=\"status green\"></span><span class=\"status-label-text\">"  . $value . "</span> </span>";;
        } else {
            return "<span class=\"status-label red\"><span class=\"status red\"></span><span class=\"status-label-text\">" . $value . "</span> </span>";
        }
    }

    /**
     * @inheritDoc
     */
    public function normalizeValue($value, ElementInterface $element = null): ExtendedLightswitchModel
    {
        if (is_array($value)) {
            $value = array_merge([
                'selected' => false,
                'comment' => ''
            ], $value);
            $value = new ExtendedLightswitchModel($value);
        }

        if ($value instanceof ExtendedLightswitchModel) {
            return $value;
        }

        return new ExtendedLightswitchModel();
    }


    /**
     * @inheritDoc
     */
    public function serializeValue($value, ElementInterface $element = null): array
    {
        return [
            'selected' => $value->selected ?? false,
            'comment' =>  $value->comment ?? '',
        ];
    }


    /**
     * @inheritDoc
     */
    public function getInlineInputHtml(mixed $value, ?ElementInterface $element): string
    {
        return $this->getInputHtml($value, $element);
    }

    /**
     * @inheritDoc
     */
    public function getInputHtml($value, ElementInterface $element = null): string
    {
        return Craft::$app->getView()->renderTemplate('_extras/_fields/extended-lightswitch-input', [
            'field' => $this,
            'value' => $value,
            'element' => $element,
            'selectedSite' => Cp::requestedSite()
        ]);
    }

    /**
     * @inheritDoc
     */
    public function getSearchKeywords(mixed $value, ElementInterface $element): string
    {

        if (!$value->selected) {
            return '';
        }

        return $this->name . ' ' . $value->comment;

    }

}
