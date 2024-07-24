# JsonCustomFieldHelper Documentation

Created by ChatGPT: 2024-07-25

## Overview

`JsonCustomFieldHelper` is a helper class for handling JSON columns within a Craft CMS context. It provides various methods for generating SQL, fetching fields, and managing field layouts.

## Namespace

```php
namespace wsydney76\extras\helpers;
```

## Dependencies

- Craft CMS
- PHP
- Yii2

## Class Definition

```php
class JsonCustomFieldHelper
```

## Methods

### `getCollation`

Get the collation string based on the provided collation type.

**Parameters:**
- `string $collation` - The collation type.

**Returns:**
- `string` - The corresponding collation string.

```php
public static function getCollation(string $collation): string
```

### `getValueSql`

Generate SQL for a given provider type, handle, field handle, and optional key.

**Parameters:**
- `string $providerType` - The type of provider.
- `string $providerHandle` - The handle of the provider.
- `string $fieldHandle` - The handle of the field.
- `string|null $key` - The optional key for the field.

**Returns:**
- `string` - The generated SQL string.

```php
public static function getValueSql(string $providerType, string $providerHandle, string $fieldHandle, ?string $key): string
```

### `getFieldSql`

Generate SQL for a specific field.

**Parameters:**
- `string $providerType` - The type of provider.
- `string $providerHandle` - The handle of the provider.
- `string $fieldHandle` - The handle of the field.
- `string|null $key` - The optional key for the field.

**Returns:**
- `string|null` - The SQL string for the field.

```php
public static function getFieldSql(string $providerType, string $providerHandle, string $fieldHandle, ?string $key): ?string
```

### `getField`

Get a field based on provider type, handle, and field handle.

**Parameters:**
- `string $providerType` - The type of provider.
- `string $providerHandle` - The handle of the provider.
- `string $fieldHandle` - The handle of the field.

**Returns:**
- `FieldInterface` - The field interface.

**Throws:**
- `InvalidArgumentException` - If the field is not found or not a text field.

```php
public static function getField(string $providerType, string $providerHandle, string $fieldHandle): FieldInterface
```

### `getFieldLayout`

Get the field layout based on the provider type and handle.

**Parameters:**
- `string $providerType` - The type of provider.
- `string $providerHandle` - The handle of the provider.

**Returns:**
- `FieldLayout` - The field layout.

**Throws:**
- `InvalidArgumentException` - If the provider type or layout is not found.

```php
public static function getFieldLayout(string $providerType, string $providerHandle): FieldLayout
```

### `getProviderHandles`

Get provider handles for a provider type whose field layouts have the specified field handle.

**Parameters:**
- `string $providerType` - The type of provider.
- `string $fieldHandle` - The handle of the field.

**Returns:**
- `string` - The comma-separated provider handles.

**Throws:**
- `InvalidArgumentException` - If the provider type is not implemented.

```php
public static function getProviderHandles(string $providerType, string $fieldHandle): string
```

### `getProviderHandlesWithField`

Get provider handles whose field layouts include the specified field handle.

**Parameters:**
- `array $providers` - The list of providers.
- `string $fieldHandle` - The handle of the field.

**Returns:**
- `string` - The comma-separated provider handles.

**Throws:**
- `InvalidArgumentException` - If no providers have the specified field handle.

```php
public static function getProviderHandlesWithField(array $providers, string $fieldHandle): string
```

## Usage Example

```php
use wsydney76\extras\helpers\JsonCustomFieldHelper;

// Example usage of getCollation method
$collation = JsonCustomFieldHelper::getCollation('pb');

// Example usage of getValueSql method
$sql = JsonCustomFieldHelper::getValueSql('entryType', 'blog', 'customField', 'key');

// Example usage of getField method
$field = JsonCustomFieldHelper::getField('entryType', 'blog', 'customField');

// Example usage of getFieldLayout method
$fieldLayout = JsonCustomFieldHelper::getFieldLayout('entryType', 'blog');
```

## Error Handling

The methods `getField`, `getFieldLayout`, `getProviderHandles`, and `getProviderHandlesWithField` may throw an `InvalidArgumentException` if the specified parameters are invalid or not found. It is recommended to use try-catch blocks to handle these exceptions appropriately.

```php
try {
    $field = JsonCustomFieldHelper::getField('entryType', 'blog', 'customField');
} catch (InvalidArgumentException $e) {
    // Handle the exception
}
```