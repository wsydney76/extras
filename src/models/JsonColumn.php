<?php

namespace wsydney76\extras\models;

use Craft;
use craft\base\FieldInterface;
use craft\base\Model;
use yii\base\InvalidArgumentException;
use function str_contains;

/**
 * Json Column model
 */
class JsonColumn extends Model
{
    public string $providerType = 'entryType';
    public string $providerHandle;

    public string $fieldHandle;

    public ?string $key = null;

    public string $sql;

    public string $collation = 'utf8mb4_0900_ai_ci';


    public function __construct($fieldIdent, $collation='ci', $config = [])
    {

        $this->parse_string($fieldIdent);

        $this->collation = $this->getCollation($collation);

        $this->sql = $this->getSql();

        parent::__construct($config);
    }

    public function equals(string $term)
    {
        return sprintf(
            "(%s) COLLATE %s = '%s' COLLATE %s",
            $this->sql,
            $this->collation,
            str_replace("'", "''", $term),
            $this->collation
        );
    }

    public function orderBy()
    {
        return sprintf(
            '(%s COLLATE %s)',
            $this->sql,
            $this->collation
        );

    }

    public function getSql(): string
    {
        $providerHandles = explode(',', $this->providerHandle);
        if (count($providerHandles) === 1) {
            return $this->getFieldSql($this->providerHandle);
        }

        $columnSql = [];
        foreach ($providerHandles as $providerHandle) {
            $columnSql[] = $this->getFieldSql($providerHandle);
        }

        return sprintf('COALESCE(%s)', implode(', ', $columnSql));
    }

    public function getFieldSql(string $providerHandle): ?string
    {
        return $this->getField($providerHandle)->getValueSql($this->key);
    }

    public function getField(string $providerHandle): FieldInterface
    {

        switch ($this->providerType) {
            case 'entryType':
                $provider = Craft::$app->getEntries()->getEntryTypeByHandle($providerHandle);
                break;
            case 'volume':
                $provider = Craft::$app->getVolumes()->getVolumeByHandle($providerHandle);
                break;
            default:
                throw new InvalidArgumentException("Provider type not found:  $this->providerType");
        }

        if (!$provider) {
            throw new InvalidArgumentException("Provider not found:  $this->providerType  $providerHandle");
        }


        $field = $provider->getFieldLayout()->getFieldByHandle($this->fieldHandle);
        if (!$field) {
            throw new InvalidArgumentException("Field not found: $this->fieldHandle");
        }


        return $field;
    }

    private function getCollation($collation): string
    {
        return match ($collation) {
            'pb' => 'utf8mb4_de_pb_0900_ai_ci',
            'ci' => 'utf8mb4_0900_ai_ci',
            'cs' => 'utf8mb4_0900_as_cs',
            default => $collation,
        };
    }

    private function getProviderHandles(mixed $fieldHandle)
    {
        switch ($this->providerType) {
            case 'entryType':
                return $this->getProviderTypeHandles(Craft::$app->getEntries()->getAllEntryTypes(), $fieldHandle);

            case 'volume':
                return $this->getProviderTypeHandles(Craft::$app->getVolumes()->getAllVolumes(), $fieldHandle);

            default:
                throw new InvalidArgumentException("Provider type not implemented:  $this->providerType");
        }
    }

    private function getProviderTypeHandles(array $providers, $fieldHandle): string
    {
        // TODO: Check if this is the best way to get all  types

        $handles = [];
        foreach ($providers as $provider) {
            if ($field = $provider->getFieldLayout()->getFieldByHandle($fieldHandle)) {
                if ($field->getValueSql() === null) {
                    continue;
                }
                $handles[] = $provider->handle;
            }
        }

        if (empty($handles)) {
            throw new InvalidArgumentException("Field not found or not content field: $fieldHandle");
        }

        return implode(',', $handles);
    }


    private function parse_string($input): void
    {

        // providerType:providerHandle.fieldHandle>key

        $parts = explode(':', $input);
        if (count($parts) === 2) {
            [$this->providerType, $input] = $parts;
        }

        $parts = explode('>', $input);
        if (count($parts) === 2) {
            [$input, $this->key] = $parts;
        }

        $parts = explode('.', $input);
        if (count($parts) === 2) {
            [$this->providerHandle, $this->fieldHandle] = $parts;
        } else {
            $this->providerHandle = $this->getProviderHandles($input);
            $this->fieldHandle = $input;
        }

    }


}
