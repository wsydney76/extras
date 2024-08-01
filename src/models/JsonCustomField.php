<?php

namespace wsydney76\extras\models;

use Craft;
use craft\base\Component;
use craft\base\FieldInterface;
use craft\db\Table;
use craft\elements\Address;
use craft\elements\User;
use craft\models\FieldLayout;
use Exception;
use wsydney76\extras\events\GetFieldsEvent;
use yii\base\InvalidArgumentException;

/**
 * JsonCustomField class provides functionality to handle JSON custom fields within a Craft CMS environment.
 *
 * This class offers methods for generating SQL fragments, such as equality and order by statements,
 * based on JSON custom fields, and provides support for MySQL databases. It is particularly useful
 * for scenarios where JSON data is stored within a field and needs to be queried efficiently.
 *
 * The class also handles the parsing of a field identifier to extract relevant parts, such as provider type,
 * provider handle, field handle, and key.
 *
 * Example usage:
 *
 * ```php
 * $jsonCustomField = new JsonCustomField('entryType:providerHandle.fieldHandle>key', 'ci');
 * $sqlEquality = $jsonCustomField->equals('searchTerm');
 * $sqlOrderBy = $jsonCustomField->orderBy();
 * ```
 *
 * @package wsydney76\extras\models
 *
 * @property-read string $indexName
 * @property-read null[]|\craft\base\FieldInterface[] $addressFields
 * @property-read array $assetFields
 * @property-read null[]|array|\craft\base\FieldInterface[] $fields
 * @property-read array $entryFields
 * @property-read null[]|\craft\base\FieldInterface[] $userFields
 * @property-read mixed $functionalIndexSql
 */
class JsonCustomField extends Component
{
    public const EVENT_GET_FIELDS = 'getFieldsEvent';

    public string $fieldIdent;

    /**
     * @var string The type of the provider (defaults to 'entryType', can be 'entryType/volume/user')
     */
    public string $providerType = 'entry';

    /**
     * @var string The handle of the field layout provider (ignored for User fields)
     */
    public string $providerHandles = '';

    /**
     * @var string The handle of the field as used/overwritten in the field layout.
     */
    public string $fieldHandle;


    /**
     * @var ?string The key within the JSON data (unused for built-in Craft text fields but could be used for nested keys)
     */
    public ?string $key = null;

    /**
     * @var string The generated SQL string for the field value
     */
    public string $valueSql;

    /**
     * @var string The collation setting used for generating SQL fragments
     */
    public string $collation;

    /**
     * JsonColumn constructor.
     *
     * @param string $fieldIdent The field identifier in the format providerType:providerHandle.fieldHandle>key
     * @param string $collation The collation type (default is 'ci'). Either a full collation string or one of the following: 'pb', 'ci', 'cs'
     * @param array $config Additional configuration options
     * @throws Exception
     */
    public function __construct(string $fieldIdent, string $collation = 'ci', array $config = [])
    {
        if (Craft::$app->getDb()->getIsMysql() === false) {
            throw new \Exception('This feature is currently only implemented for MySQL databases.');
        }

        $this->fieldIdent = $fieldIdent;

        // Parse the field identifier to set provider type, provider handle, field handle, and key
        $this->parseFieldIdent($fieldIdent);


        // Set the collation based on the provided type
        $this->collation = $this->getCollation($collation);

        // Generate the SQL string for the field(s)
        $this->valueSql = $this->getFieldValueSql($this->getFields());

        parent::__construct($config);
    }


    private function parseFieldIdent(string $fieldIdent): void
    {
        // Split the field identifier by ':' to separate provider type
        $parts = explode(':', $fieldIdent);
        if (count($parts) > 2) {
            throw new InvalidArgumentException('Invalid field identifier format: ' . $fieldIdent);
        }

        if (count($parts) === 2) {
            [$this->providerType, $fieldIdent] = $parts;
        }

        // Split the remaining identifier by '>' to separate the key
        $parts = explode('>', $fieldIdent);
        if (count($parts) > 2) {
            throw new InvalidArgumentException('Invalid field identifier format: ' . $fieldIdent);
        }
        if (count($parts) === 2) {
            [$fieldIdent, $this->key] = $parts;
        }

        // Split the remaining identifier by '.' to separate the provider handle and field handle
        $parts = explode('.', $fieldIdent);
        if (count($parts) > 2) {
            throw new InvalidArgumentException('Invalid field identifier format: ' . $fieldIdent);
        }

        if (count($parts) === 2) {
            [$this->providerHandles, $this->fieldHandle] = $parts;
        } else {
            // If no '.' is found, use the field identifier as the field handle
            $this->fieldHandle = $fieldIdent;
        }
    }

    public function getCollation(string $collation): string
    {
        return match ($collation) {
            'pb' => 'utf8mb4_de_pb_0900_ai_ci',
            'ci' => 'utf8mb4_0900_ai_ci',
            'cs' => 'utf8mb4_0900_as_cs',
            'bin' => 'utf8mb4_bin',
            default => $collation,
        };
    }

    /* =============================================================== */
    /* SQL Fragment Functions                                          */
    /* =============================================================== */

    /**
     * Generates an SQL equality statement.
     *
     * @param string $term The term to compare against
     * @return string The generated SQL equality statement
     */
    public function equals(string $term): string
    {
        // Return an SQL equality statement with collation

        $sql = $this->valueSql;

        return sprintf(
            "(%s COLLATE %s) = '%s'",
            $sql,
            $this->collation,
            str_replace("'", "''", $term),

        );
    }

    /**
     * Generates an SQL ORDER BY statement.
     *
     * @return string The generated SQL ORDER BY statement
     */
    public function orderBy(): string
    {
        // Return an SQL ORDER BY statement with collation
        return sprintf(
            '(%s COLLATE %s)',
            $this->valueSql,
            $this->collation
        );
    }

    public function relatedTo(int|array $ids): string|array
    {

        if (!is_array($ids)) {
            return $this->getContainsCondition($ids);
        }

        $conditions = ['or'];

        foreach ($ids as $id) {
            $conditions[] = $this->getContainsCondition($id);
        }

        return $conditions;
    }


    /**
     * @param int $id
     * @return string
     */
    private function getContainsCondition(int $id): string
    {
        return sprintf(
            "JSON_CONTAINS(%s, '%s')",
            $this->valueSql,
            $id
        );
    }


    public function getFunctionalIndexSql(): string
    {
        // alter table elements_sites add index idx_lastname ((  cast((`elements_sites`.`content`->>'$.\"0f4660e2-5304-4c08-a85d-a82cc9f7c47d\"') as char(255)) collate utf8mb4_0900_ai_ci));

        return sprintf(
            "alter table %s add index %s (( %s collate %s )) USING BTREE;",
            Table::ELEMENTS_SITES,
            $this->getIndexName(),
            $this->valueSql,
            $this->collation
        );
    }

    /**
     * Parses the field identifier and sets the provider type, provider handle, field handle, and key.
     *
     * @param string $fieldIdent The field identifier in the format providerType:providerHandle.fieldHandle>key
     * @return void
     */


    private function getIndexName(): string
    {
        return 'idx_field_' . str_replace(['.', ':', ',', '>'], '_', $this->fieldIdent . '_' . $this->collation);
    }

    /* =============================================================== */
    /* Field Value Functions                                           */
    /* =============================================================== */

    public function getFields(): array
    {
        $fields = match ($this->providerType) {
            'entry' => $this->getEntryFields(),
            'asset' => $this->getAssetFields(),
            'user' => $this->getFieldsFromSingleLayout(User::class),
            'address' => $this->getFieldsFromSingleLayout(Address::class),
            default => null,
        };

        if (!$fields) {
            if ($this->hasEventHandlers(self::EVENT_GET_FIELDS)) {
                $event = new GetFieldsEvent([
                    'providerType' => $this->providerType,
                    'providerHandles' => $this->providerHandles,
                    'fieldHandle' => $this->fieldHandle,
                ]);

                $this->trigger(self::EVENT_GET_FIELDS, $event);

                $fields = $event->fields;
            }

            if (!$fields) {
                throw new InvalidArgumentException("No field found: $this->fieldIdent");
            }
        }
        return $fields;
    }

    private function getFieldValueSql(array $fields): string
    {
        if (count($fields) === 1) {
            return $this->getSingleValueFieldSql($fields[0]);
        }

        $singleFieldSQL = [];
        foreach ($fields as $field) {
            $singleFieldSQL[] = $this->getSingleValueFieldSql($field);
        }

        return sprintf('COALESCE(%s)', implode(', ', $singleFieldSQL));
    }

    /**
     * @param mixed $field
     * @return string
     */
    private function getSingleValueFieldSql(mixed $field): string
    {
        $sql = $field->getValueSql($this->key);
        if (!str_starts_with($sql, 'CAST') && $field::dbType() === 'text') {
            $sql = "CAST($sql AS CHAR(255))";
        }
        return $sql;
    }

    /* =============================================================== */
    /* Provider type specific Functions                                */
    /* =============================================================== */


    public function getEntryFields(): array
    {
        if ($this->providerHandles === '*' || !$this->providerHandles) {
            $entryTypeCandidates = Craft::$app->getEntries()->getAllEntryTypes();
        } else {
            $entryTypeCandidates = [];

            foreach (explode(',', $this->providerHandles) as $entryTypeHandle) {
                $entryType = Craft::$app->getEntries()->getEntryTypeByHandle($entryTypeHandle);
                if (!$entryType) {
                    throw new \InvalidArgumentException("Entry type not found: $entryTypeHandle");
                }
                $entryTypeCandidates[] = $entryType;
            }
        }


        return static::getFieldsFromCandidates($entryTypeCandidates, $this->fieldHandle);
    }

    public function getAssetFields(): array
    {
        $volumeCandidates = [];
        if ($this->providerHandles === '*' || !$this->providerHandles) {
            $volumeCandidates = Craft::$app->getVolumes()->getAllVolumes();
        } else {
            foreach (explode(',', $this->providerHandles) as $volumeHandle) {
                $volume = Craft::$app->getVolumes()->getVolumeByHandle($volumeHandle);
                if (!$volume) {
                    throw new \InvalidArgumentException("Volume not found: $volumeHandle");
                }
                $volumeCandidates[] = $volume;
            }
        }

        return static::getFieldsFromCandidates($volumeCandidates, $this->fieldHandle);
    }

    public function getFieldsFromSingleLayout($class): array
    {
        $layout = Craft::$app->getFields()->getLayoutByType($class);

        return [static::getFieldFromLayout($layout, $this->fieldHandle)];
    }


    /* =============================================================== */
    /* Static Functions                                                */
    /* =============================================================== */


    public static function getFieldsFromCandidates(array $candidates, string $fieldHandle): array
    {
        $fields = [];

        foreach ($candidates as $candidate) {
            $field = static::getFieldFromLayout($candidate->getFieldLayout(), $fieldHandle);
            if ($field) {
                $fields[] = $field;
            }
        }

        if (empty($fields)) {
            throw new \InvalidArgumentException("Field not found: $fieldHandle");
        }

        return $fields;
    }

    /**
     * @param mixed $fieldLayout
     * @param string $fieldHandle
     * @return FieldInterface|null
     */
    private static function getFieldFromLayout(FieldLayout $fieldLayout, string $fieldHandle): ?FieldInterface
    {
        $field = $fieldLayout->getFieldByHandle($fieldHandle);

        if ($field && !in_array($field::dbType(), ['json', 'text', 'decimal(65,16)'])) {
            throw new \InvalidArgumentException("Field is not a JSON,TEXT or DECIMAL field: $fieldHandle");
        }
        return $field;
    }
}
