<?php

namespace wsydney76\extras\helpers;

use Craft;
use craft\base\Component;
use craft\base\FieldLayoutProviderInterface;
use yii\base\InvalidArgumentException;

class ExtrasHelper extends Component
{


    /**
     * Creates a SQL condition for ordering by a JSON column with custom collation.
     *
     * @param string|FieldLayoutProviderInterface $provider
     * @param string $fieldHandle
     * @param string $collation
     * @param string|null $key
     * @return string
     */
    public static function orderByJsonColumn(string|FieldLayoutProviderInterface $provider, string $fieldHandle, string $collation = 'ci', ?string $key = null): string
    {

        return sprintf(
            '(%s COLLATE %s)',
            static::getFieldSql($provider, $fieldHandle, $key),
            match ($collation) {
                'pb' => 'utf8mb4_de_pb_0900_ai_ci',
                'ci' => 'utf8mb4_0900_ai_ci',
                'cs' => 'utf8mb4_0900_as_cs',
                default => $collation,
            }
        );
    }

    /**
     * Creates a SQL condition for comparing a JSON column in lower case.
     *
     * @param FieldLayoutProviderInterface|string $provider
     * @param string $fieldHandle
     * @param string $term
     * @param string|null $key
     * @return string
     */
    public static function equalsLower(FieldLayoutProviderInterface|string $provider, string $fieldHandle, string $term, ?string $key): string
    {

        return sprintf(
            "LOWER(%s) = LOWER('%s')",
            static::getFieldSql($provider, $fieldHandle, $key),
            str_replace("'", "''", $term)
        );
    }

    public static function getFieldSql(FieldLayoutProviderInterface|string $provider, string $fieldHandle, ?string $key): ?string
    {
        return static::getField($provider, $fieldHandle)->getValueSql($key);
    }

    public static function getField(FieldLayoutProviderInterface|string $provider, string $fieldHandle): \craft\base\FieldInterface
    {
        if (is_string($provider)) {
            $provider = Craft::$app->getEntries()->getEntryTypeByHandle($provider);
            if (!$provider) {
                throw new InvalidArgumentException('Entry type not found: ' . $provider);
            }
        }

        $field = $provider->getFieldLayout()->getFieldByHandle($fieldHandle);
        if (!$field) {
            throw new InvalidArgumentException('Field not found: ' . $fieldHandle);
        }

        return $field;
    }
}