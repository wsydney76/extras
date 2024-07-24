# JsonCustomField Class Documentation

Created by ChatGPT: 2024-07-25

## Namespace
`wsydney76\extras\models`

## Dependencies
- `Craft`
- `craft\base\Model`
- `craft\db\Table`
- `wsydney76\extras\helpers\JsonCustomFieldHelper`

## Overview
The `JsonCustomField` class provides functionality to handle JSON custom fields within a Craft CMS environment. This class offers methods for generating SQL fragments, such as equality and order by statements, based on JSON custom fields, and provides support for MySQL databases. It is particularly useful for scenarios where JSON data is stored within a field and needs to be queried efficiently.

The class also handles the parsing of a field identifier to extract relevant parts, such as provider type, provider handle, field handle, and key.

### Example Usage
```php
$jsonCustomField = new JsonCustomField('entryType:providerHandle.fieldHandle>key', 'ci');
$sqlEquality = $jsonCustomField->equals('searchTerm');
$sqlOrderBy = $jsonCustomField->orderBy();
```

```twig
{# Example Twig usage #}
{% set query = craft.entries
    .section('person')
    .andWhere(field('lastName','pb').equals(term))
    .orderBy({
        (field('firstName').orderBy()): SORT_ASC
    })
%}
```

Twig function provided by the `ExtrasExtension` class.

## Properties
### public string $fieldIdent
The field identifier.

### public string $providerType
The type of the provider. Defaults to `'entryType'`, but can also be `'entryType/volume/user'`.

### public string $providerHandle
The handle of the field layout provider. Ignored for user fields.

### public string $fieldHandle
The handle of the field as used/overwritten in the field layout.

### public ?string $key
The key within the JSON data. Unused for built-in Craft text fields but could be used for nested keys.

### public string $valueSql
The generated SQL string for the field value.

### public string $collation
The collation setting used for generating SQL fragments.

## Methods

### __construct(string $fieldIdent, string $collation = 'ci', array $config = [])
Constructor to initialize the `JsonCustomField` instance.

- **Parameters:**
    - `string $fieldIdent`: The field identifier in the format `providerType:providerHandle.fieldHandle>key`.
    - `string $collation`: The collation type (default is `'ci'`). Either a full collation string or one of the following: `'pb'`, `'ci'`, `'cs'`.
    - `array $config`: Additional configuration options.

### equals(string $term): string
Generates an SQL equality statement.

- **Parameters:**
    - `string $term`: The term to compare against.
- **Returns:**
    - `string`: The generated SQL equality statement.

### orderBy(): string
Generates an SQL `ORDER BY` statement.

- **Returns:**
    - `string`: The generated SQL `ORDER BY` statement.

### relatedTo(int|array $ids): string|array
Generates an SQL `RELATED TO` condition.

- **Parameters:**
    - `int|array $ids`: The ID(s) to relate to.
- **Returns:**
    - `string|array`: The generated SQL `RELATED TO` condition.

### parseFieldIdent(string $fieldIdent): void
Parses the field identifier and sets the provider type, provider handle, field handle, and key.

- **Parameters:**
    - `string $fieldIdent`: The field identifier in the format `providerType:providerHandle.fieldHandle>key`.
- **Returns:**
    - `void`

### getFunctionalIndexSql()
Generates SQL for creating a functional index.

- **Returns:**
    - `string`: The generated SQL for the functional index.

### getIndexName()
Generates the index name.

- **Returns:**
    - `string`: The generated index name.

### protected getContainsCondition(int $id): string
Generates an SQL condition for checking JSON containment.

- **Parameters:**
    - `int $id`: The ID to check containment for.
- **Returns:**
    - `string`: The generated SQL condition.

## Properties (Read-Only)
### string $indexName
The generated index name.

### mixed $functionalIndexSql
The generated SQL for the functional index.