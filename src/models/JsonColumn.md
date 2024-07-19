# JsonColumn Model Documentation

## Overview

The `JsonColumn` model class is designed to handle specific operations related to JSON data stored in text fields within Craft CMS. This model allows for SQL generation, collation settings, and parsing of field identifiers in order to interact with fields effectively.

Tested with MySql 8 only.

## Properties

**Provider** stands for anything that defines a field layout. 

- **providerType** (`string`): The type of the field layout provider (default is 'entryType'). Possible values are `entryType`, `volume`, `user` for now.
- **providerHandle** (`string`): The handle of the field layout provider. Ignored if the provider type is 'user'. If empty, all provider handles that use the specified field will be set.
- **fieldHandle** (`string`): The handle of the field.
- **key** (`?string`): The key within the JSON data (optional).
- **sql** (`string`): The generated SQL string for the field.
- **collation** (`string`): The collation setting for the SQL (default is 'utf8mb4_0900_ai_ci').

## Methods

### Constructor

```php
__construct($fieldIdent, $collation = 'ci', $config = [])
```

Initializes the model with a field identifier and optional collation.

- **$fieldIdent** (`string`): The field identifier in the format `providerType:providerHandle.fieldHandle>key`.
- **$collation** (`string`): The collation type (default is 'ci').
- **$config** (`array`): Additional configuration options.

### equals

```php
equals(string $term): string
```

Generates an SQL equality statement.

- **$term** (`string`): The term to compare against.
- **Returns**: (`string`) The generated SQL equality statement.

### orderBy

```php
orderBy(): string
```

Generates an SQL ORDER BY statement.

- **Returns**: (`string`) The generated SQL ORDER BY statement.

### getSql

```php
getSql(): void
```

Generates the SQL string based on the provider handles and field.

### getFieldSql

```php
getFieldSql(string $providerHandle): ?string
```

Generates the SQL string for a specific provider handle.

- **$providerHandle** (`string`): The provider handle.
- **Returns**: (`?string`) The SQL string for the provider handle.

### getField

```php
getField(string $providerHandle): FieldInterface
```

Retrieves the field interface for a given provider handle.

- **$providerHandle** (`string`): The provider handle.
- **Returns**: (`FieldInterface`) The field interface.
- **Throws**: `InvalidArgumentException` if the provider or field is not found or the field is not a text field.

### getCollation

```php
getCollation($collation): void
```

Sets the collation based on the provided type.

- **$collation** (`string`): The collation type.

### getProviderHandles

```php
getProviderHandles(mixed $fieldHandle): string
```

Retrieves the provider handles for a given field handle.

- **$fieldHandle** (`mixed`): The field handle.
- **Returns**: (`string`) The provider handles as a comma-separated string.
- **Throws**: `InvalidArgumentException` if the provider type is not implemented.

### getProviderTypeHandles

```php
getProviderTypeHandles(array $providers, $fieldHandle): string
```

Retrieves the handles for a given provider type and field handle.

- **$providers** (`array`): The array of providers.
- **$fieldHandle** (`mixed`): The field handle.
- **Returns**: (`string`) The provider type handles as a comma-separated string.
- **Throws**: `InvalidArgumentException` if the field is not found.

### parseFieldIdent

```php
parseFieldIdent($fieldIdent): void
```

Parses the field identifier and sets the provider type, provider handle, field handle, and key.

- **$fieldIdent** (`string`): The field identifier in the format `providerType:providerHandle.fieldHandle>key`.

## Usage

To use the `JsonColumn` model, you need to create an instance of the class with the appropriate field identifier and optional collation. You can then call its methods to generate SQL statements or retrieve field information.

### Example

```php
use wsydney76\extras\models\JsonColumn;

$fieldIdent = 'entryType:blog.entryTitle>author';
$collation = 'ci';

$jsonColumn = new JsonColumn($fieldIdent, $collation);

// Generate SQL equality statement
$sqlEquals = $jsonColumn->equals('John Doe');

// Generate SQL ORDER BY statement
$sqlOrderBy = $jsonColumn->orderBy();
```

In this example, the `JsonColumn` model is instantiated with a specific field identifier and collation. The `equals` and `orderBy` methods are used to generate SQL statements for querying and sorting the JSON data in the specified field.

Ensure that the field identifiers and provider types used in your code match the structure and data types defined in your Craft CMS setup.

In Twig via 'jsonColum' custom twig function:

```twig
.andWhere(jsonColumn('lastName', 'cs').equals(term))

.orderBy({
    (jsonColumn('person.lastName', 'pb').orderBy()): SORT_ASC,
    (jsonColumn('person.firstName', 'pb').orderBy()): SORT_ASC,
    namePrefix: SORT_ASC
})
```