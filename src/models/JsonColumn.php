<?php

namespace wsydney76\extras\models;

use Craft;
use craft\base\FieldInterface;
use craft\base\Model;
use craft\elements\User;
use yii\base\InvalidArgumentException;

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
 * @property string $sql The generated SQL string for the field
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
    public string $providerType = 'entryType';
    public string $providerHandle;
    public string $fieldHandle;
    public ?string $key = null;
    public string $sql;
    public string $collation = 'utf8mb4_0900_ai_ci';

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
        $this->getCollation($collation);
        // Generate the SQL string for the field
        $this->getSql();

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
            $this->sql,
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
            $this->sql,
            $this->collation
        );
    }

    /**
     * Generates the SQL string based on the provider handles and field.
     *
     * @return void
     */
    public function getSql(): void
    {
        // Split the provider handles by comma
        $providerHandles = explode(',', $this->providerHandle);
        if (count($providerHandles) === 1) {
            // Generate SQL for a single provider handle
            $this->sql = $this->getFieldSql($this->providerHandle);
            return;
        }

        // Generate SQL for multiple provider handles and combine using COALESCE
        $sql = [];
        foreach ($providerHandles as $providerHandle) {
            $sql[] = $this->getFieldSql($providerHandle);
        }

        $this->sql = sprintf('COALESCE(%s)', implode(', ', $sql));
    }

    /**
     * Generates the SQL string for a specific provider handle.
     *
     * @param string $providerHandle The provider handle
     * @return ?string The SQL string for the provider handle
     */
    public function getFieldSql(string $providerHandle): ?string
    {
        // Retrieve the field SQL string for the specified provider handle
        return $this->getField($providerHandle)->getValueSql($this->key);
    }

    /**
     * Retrieves the field interface for a given provider handle.
     *
     * @param string $providerHandle The provider handle
     * @return FieldInterface The field interface
     * @throws InvalidArgumentException If the provider or field is not found or the field is not a text field
     */
    public function getField(string $providerHandle): FieldInterface
    {
        // Retrieve the field layout based on the provider type
        $layout = match ($this->providerType) {
            'entryType' => Craft::$app->getEntries()->getEntryTypeByHandle($providerHandle)?->getFieldLayout(),
            'volume' => Craft::$app->getVolumes()->getVolumeByHandle($providerHandle)?->getFieldLayout(),
            'user' => Craft::$app->getFields()->getLayoutByType(User::class),
            default => throw new InvalidArgumentException("Provider type not found:  $this->providerType"),
        };

        // Check if the layout was found
        if (!$layout) {
            throw new InvalidArgumentException("Field layout provider not found: $this->providerType $providerHandle");
        }

        // Retrieve the field from the layout
        $field = $layout->getFieldByHandle($this->fieldHandle);

        // Check if the field was found
        if (!$field) {
            throw new InvalidArgumentException("Field not found: $this->fieldHandle");
        }

        // Ensure the field is a text field
        if ($field::dbType() !== 'text') {
            throw new InvalidArgumentException("Field is not a text field: $this->fieldHandle");
        }

        return $field;
    }

    /**
     * Sets the collation based on the provided type.
     *
     * @param string $collation The collation type
     * @return void
     */
    private function getCollation(string $collation): void
    {
        // Match the provided collation type to the corresponding collation string
        $this->collation = match ($collation) {
            'pb' => 'utf8mb4_de_pb_0900_ai_ci',
            'ci' => 'utf8mb4_0900_ai_ci',
            'cs' => 'utf8mb4_0900_as_cs',
            'bin' => 'utf8mb4_bin',
            default => $collation,
        };
    }

    /**
     * Retrieves the provider handles for a given field handle.
     *
     * @return string The provider handles as a comma-separated string
     * @throws InvalidArgumentException If the provider type is not implemented
     */
    private function getProviderHandles(): string
    {
        // Retrieve the provider handles based on the provider type
        return match ($this->providerType) {
            'entryType' => $this->getProviderTypeHandles(Craft::$app->getEntries()->getAllEntryTypes()),
            'volume' => $this->getProviderTypeHandles(Craft::$app->getVolumes()->getAllVolumes()),
            'user' => 'user',
            default => throw new InvalidArgumentException("Provider type not implemented:  $this->providerType"),
        };
    }

    /**
     * Retrieves the handles for a given provider type.
     *
     * @param array $providers The array of providers
     * @return string The provider type handles as a comma-separated string
     * @throws InvalidArgumentException If the field is not found
     */
    private function getProviderTypeHandles(array $providers): string
    {
        // Initialize an array to hold the handles
        $handles = [];
        foreach ($providers as $provider) {
            // Check if the provider has the specified field handle
            if ($provider->getFieldLayout()->getFieldByHandle($this->fieldHandle)) {
                $handles[] = $provider->handle;
            }
        }

        // Check if any handles were found
        if (empty($handles)) {
            throw new InvalidArgumentException("Field not found: $this->fieldHandle");
        }

        // Return the handles as a comma-separated string
        return implode(',', $handles);
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
            $this->providerHandle = $this->getProviderHandles();
        }
    }
}
