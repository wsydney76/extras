<?php

namespace wsydney76\extras\helpers;

use Craft;
use craft\base\FieldInterface;
use craft\elements\User;
use craft\models\FieldLayout;
use yii\base\InvalidArgumentException;
use function in_array;

/**
 * Class JsonColumnHelper
 * Provides helper methods for handling JSON columns in a Craft CMS context.
 *
 * @package wsydney76\extras\helpers
 */
class JsonColumnHelper
{
    /**
     * Get the collation string based on the provided collation type.
     *
     * @param string $collation The collation type.
     * @return string The corresponding collation string.
     */
    public static function getCollation(string $collation): string
    {
        return match ($collation) {
            'pb' => 'utf8mb4_de_pb_0900_ai_ci',
            'ci' => 'utf8mb4_0900_ai_ci',
            'cs' => 'utf8mb4_0900_as_cs',
            'bin' => 'utf8mb4_bin',
            default => $collation,
        };
    }

    /**
     * Generate SQL for a given provider type, handle, field handle, and optional key.
     *
     * @param string $providerType The type of provider.
     * @param string $providerHandle The handle of the provider.
     * @param string $fieldHandle The handle of the field.
     * @param string|null $key The optional key for the field.
     * @return string The generated SQL string.
     */
    public static function getValueSql(string $providerType, string $providerHandle, string $fieldHandle, ?string $key): string
    {
        if (!$providerHandle || $providerHandle === '*') {
            $providerHandle = static::getProviderHandles($providerType, $fieldHandle);
        }

        $providerHandles = explode(',', $providerHandle);

        // If there is only one provider, return the SQL for that provider
        if (count($providerHandles) === 1) {
            return static::getFieldSql($providerType, $providerHandle, $fieldHandle, $key);
        }

        // If there are multiple providers, get the SQL for each provider and coalesce them
        $sql = [];
        foreach ($providerHandles as $currentProviderHandle) {
            $sql[] = static::getFieldSql($providerType, $currentProviderHandle, $fieldHandle, $key);
        }

        return sprintf('COALESCE(%s)', implode(', ', $sql));
    }

    /**
     * Generate SQL for a specific field.
     *
     * @param string $providerType The type of provider.
     * @param string $providerHandle The handle of the provider.
     * @param string $fieldHandle The handle of the field.
     * @param string|null $key The optional key for the field.
     * @return string|null The SQL string for the field.
     */
    public static function getFieldSql(string $providerType, string $providerHandle, string $fieldHandle, ?string $key): ?string
    {
        return static::getField($providerType, $providerHandle, $fieldHandle)->getValueSql($key);
    }

    /**
     * Get a field based on provider type, handle, and field handle.
     *
     * @param string $providerType The type of provider.
     * @param string $providerHandle The handle of the provider.
     * @param string $fieldHandle The handle of the field.
     * @return FieldInterface The field interface.
     * @throws InvalidArgumentException If the field is not found or not a text field.
     */
    public static function getField(string $providerType, string $providerHandle, string $fieldHandle): FieldInterface
    {
        $layout = static::getFieldLayout($providerType, $providerHandle);
        $field = $layout->getFieldByHandle($fieldHandle);

        if (!$field) {
            throw new InvalidArgumentException("Field not found: $fieldHandle");
        }


        if (!in_array($field::dbType(), ['text', 'json'])) {
            throw new InvalidArgumentException("'{$field::dbType()}' is not a valid field type: $fieldHandle");
        }

        return $field;
    }

    /**
     * Get the field layout based on the provider type and handle.
     *
     * @param string $providerType The type of provider.
     * @param string $providerHandle The handle of the provider.
     * @return FieldLayout The field layout.
     * @throws InvalidArgumentException If the provider type or layout is not found.
     */
    public static function getFieldLayout(string $providerType, string $providerHandle): FieldLayout
    {
        $layout = match ($providerType) {
            'entryType' => Craft::$app->getEntries()->getEntryTypeByHandle($providerHandle)?->getFieldLayout(),
            'volume' => Craft::$app->getVolumes()->getVolumeByHandle($providerHandle)?->getFieldLayout(),
            'user' => Craft::$app->getFields()->getLayoutByType(User::class),
            default => throw new InvalidArgumentException("Provider type not found:  $providerType"),
        };

        if (!$layout) {
            throw new InvalidArgumentException("Field layout provider not found: $providerType $providerHandle");
        }
        return $layout;
    }

    /**
     * Get provider handles for provider type whose field layouts have the specified field handle.
     *
     * @param string $providerType The type of provider.
     * @param string $fieldHandle The handle of the field.
     * @return string The comma-separated provider handles.
     * @throws InvalidArgumentException If the provider type is not implemented.
     */
    public static function getProviderHandles(string $providerType, string $fieldHandle): string
    {
        return match ($providerType) {
            'entryType' => static::getProviderHandlesWithField(Craft::$app->getEntries()->getAllEntryTypes(), $fieldHandle),
            'volume' => static::getProviderHandlesWithField(Craft::$app->getVolumes()->getAllVolumes(), $fieldHandle),
            'user' => 'user',
            default => throw new InvalidArgumentException("Provider type not implemented:  $providerType"),
        };
    }

    /**
     * Get provider handles whose field layouts include the specified field handle.
     *
     * @param array $providers The list of providers.
     * @param string $fieldHandle The handle of the field.
     * @return string The comma-separated provider handles.
     * @throws InvalidArgumentException If no providers have the specified field handle.
     */
    public static function getProviderHandlesWithField(array $providers, string $fieldHandle): string
    {
        $handles = [];
        foreach ($providers as $provider) {
            if ($provider->getFieldLayout()->getFieldByHandle($fieldHandle)) {
                $handles[] = $provider->handle;
            }
        }

        if (empty($handles)) {
            throw new InvalidArgumentException("Field not found: $fieldHandle");
        }

        return implode(',', $handles);
    }
}
