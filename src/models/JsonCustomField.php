<?php

namespace wsydney76\extras\models;

use Craft;
use craft\base\Model;
use craft\db\Table;
use wsydney76\extras\helpers\JsonCustomFieldHelper;

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
 * @property-read mixed $functionalIndexSql
 */
class JsonCustomField extends Model
{
    public string $fieldIdent;

    /**
     * @var string The type of the provider (defaults to 'entryType', can be 'entryType/volume/user')
     */
    public string $providerType = 'entryType';

    /**
     * @var string The handle of the field layout provider (ignored for User fields)
     */
    public string $providerHandle;

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
     */
    public function __construct(string $fieldIdent, string $collation = 'ci', array $config = [])
    {
        if (Craft::$app->getDb()->getIsMysql() === false) {
            throw new \Exception('This feature is only available for MySQL databases.');
        }

        $this->fieldIdent = $fieldIdent;

        // Parse the field identifier to set provider type, provider handle, field handle, and key
        $this->parseFieldIdent($fieldIdent);

        // Set the collation based on the provided type
        $this->collation = JsonCustomFieldHelper::getCollation($collation);

        // Generate the SQL string for the field
        $this->valueSql = JsonCustomFieldHelper::getValueSql($this->providerType, $this->providerHandle, $this->fieldHandle, $this->key);

        parent::__construct($config);
    }

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
            "(%s) COLLATE %s = '%s' COLLATE %s",
            $sql,
            $this->collation,
            str_replace("'", "''", $term),
            $this->collation
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
     * Parses the field identifier and sets the provider type, provider handle, field handle, and key.
     *
     * @param string $fieldIdent The field identifier in the format providerType:providerHandle.fieldHandle>key
     * @return void
     */
    private function parseFieldIdent(string $fieldIdent): void
    {
        // Split the field identifier by ':' to separate provider type
        $parts = explode(':', $fieldIdent);
        if (count($parts) === 2) {
            [$this->providerType, $fieldIdent] = $parts;
        }

        // Split the remaining identifier by '>' to separate the key
        $parts = explode('>', $fieldIdent);
        if (count($parts) === 2) {
            [$fieldIdent, $this->key] = $parts;
        }

        // Split the remaining identifier by '.' to separate the provider handle and field handle
        $parts = explode('.', $fieldIdent);
        if (count($parts) === 2) {
            [$this->providerHandle, $this->fieldHandle] = $parts;
        } else {
            // If no '.' is found, use the field identifier as the field handle and retrieve provider handles
            $this->fieldHandle = $fieldIdent;
            $this->providerHandle = JsonCustomFieldHelper::getProviderHandles($this->providerType, $this->fieldHandle);
        }
    }

    /**
     * @param int $id
     * @return string
     */
    protected function getContainsCondition(int $id): string
    {
        return sprintf(
            "JSON_CONTAINS(%s, '%s')",
            $this->valueSql,
            $id
        );
    }

    public function getFunctionalIndexSql()
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

    private function getIndexName()
    {
        return 'idx_field_' . str_replace(['.', ':', ',', '>'], '_', $this->fieldIdent . '_' . $this->collation) ;
    }


}
