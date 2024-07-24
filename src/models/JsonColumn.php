<?php

namespace wsydney76\extras\models;

use craft\base\Model;
use wsydney76\extras\helpers\JsonColumnHelper;

/**
 * Json Column model
 *
 * This model generates SQL statements for JSON text fields in Crafts elements_site.content column.
 * Use this model to generate SQL statements for equality and ORDER BY clauses that have to take non-default collations into account.
 *
 * @property string $providerType The type of the provider (defaults to 'entryType', can be 'entryType/volume/user')
 * @property string $providerHandle The handle of the field layout provider (ignored for User fields)
 * @property string $fieldHandle The handle of the field
 * @property ?string $key The key within the JSON data (unused for built-in Craft text fields but could be used for nested keys)
 * @property string $valueSql The generated SQL string for the field
 * @property string $collation The collation setting for the SQL
 *
 * @example
 *  ```
 *  use wsydney76\extras\models\JsonColumn;
 *
 *  // Example field identifier in the format 'providerType:providerHandle.fieldHandle>key'
 *
 * // type = entryType, all entryTypes, field = person,
 * $fieldIdent = 'firstName';
 *
 *  // type = entryType, entryTypeHandle = person or photoPerson, field = firstName
 *  $fieldIdent = 'person,photoPerson.firstName';
 *
 * // type = volume, volumeHandle = all volumes, field = volume:copyright
 *  $fieldIdent = 'volume:copyright';
 *  $collation = 'ci';
 *
 *  // Create an instance of JsonColumn
 *  $jsonColumn = new JsonColumn($fieldIdent, $collation);
 *
 *  // Generate SQL equality statement
 *  $sqlEquals = $jsonColumn->equals('John Doe');
 *  echo $sqlEquals; // Outputs the SQL equality statement
 *
 *  // Generate SQL ORDER BY statement
 *  $sqlOrderBy = $jsonColumn->orderBy();
 *  echo $sqlOrderBy; // Outputs the SQL ORDER BY statement
 *  ```
 *
 * In Twig via 'jsonColum' custom twig function:
 *
 * .andWhere(jsonColumn('lastName', 'cs').equals(term))
 *
 * .orderBy({
 *     (jsonColumn('person.lastName', 'pb').orderBy()): SORT_ASC,
 *     (jsonColumn('person.firstName', 'pb').orderBy()): SORT_ASC,
 *     namePrefix: SORT_ASC
 * })
 *
 */
class JsonColumn extends Model
{
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
        // Parse the field identifier to set provider type, provider handle, field handle, and key
        $this->parseFieldIdent($fieldIdent);

        // Set the collation based on the provided type
        $this->collation = JsonColumnHelper::getCollation($collation);

        // Generate the SQL string for the field
        $this->valueSql = JsonColumnHelper::getValueSql($this->providerType, $this->providerHandle, $this->fieldHandle, $this->key);

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
        return sprintf(
            "(%s) COLLATE %s = '%s' COLLATE %s",
            $this->valueSql,
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
            $this->providerHandle = JsonColumnHelper::getProviderHandles($this->providerType, $this->fieldHandle);
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


}
