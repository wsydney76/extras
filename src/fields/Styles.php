<?php

namespace wsydney76\extras\fields;

use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\elements\Entry;
use craft\fields\Checkboxes;
use craft\fields\data\OptionData;
use craft\fields\MultiSelect;
use craft\helpers\ArrayHelper;
use craft\helpers\Cp;
use craft\helpers\StringHelper;
use wsydney76\fields\models\PropertiesModel;
use yii\db\ExpressionInterface;
use yii\db\Schema;
use function array_map;
use function is_array;

/**
 * Properties field type
 */
class Styles extends MultiSelect
{

    public array $options = [];

    public array $styles = [];

    public string $availableClasses = '';

    public function init(): void
    {

        $options = $this->availableClasses ?:
            (Craft::$app->getConfig()->getCustom()->stylesAvailableClasses[$this->handle] ??
            Craft::$app->getConfig()->getCustom()->stylesAvailableClasses['default'] ??
            '');

        $options = array_map('trim', explode(',', $options));
        sort($options);

        $this->options = array_map(fn($option) => ['label' => $option, 'value' => $option], $options);

        parent::init();
    }

    public static function displayName(): string
    {
        return Craft::t('_extras', 'Styles');
    }

    public static function icon(): string
    {
        return 'text';
    }

    /**
     * @inheritDoc
     */
    public static function phpType(): string
    {
        return 'string';
    }

    public static function dbType(): string
    {
        // Replace with the appropriate data type this field will store in the database,
        // or `null` if the field is managing its own data storage.
        return Schema::TYPE_STRING;
    }

    public function getSettingsHtml(): ?string
    {
        return Cp::textFieldHtml([
            'label' => Craft::t('_extras', 'Available Classes'),
            'instructions' => Craft::t('_extras', 'Define the available classes as comma-seperated list.'),
            'tip' => Craft::t('_extras', 'Leave blank if available classes are defined in the custom config.'),
            'id' => 'available-classes',
            'name' => 'availableClasses',
            'value' => $this->availableClasses
        ]);
    }

}
