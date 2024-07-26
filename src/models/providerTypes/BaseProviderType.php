<?php

namespace wsydney76\extras\models\providerTypes;

use craft\base\FieldInterface;
use craft\base\FieldLayoutProviderInterface;
use craft\base\Model;
use craft\models\FieldLayout;

class BaseProviderType
{

    public function getFields(string $providerHandles, string $fieldHandle): array
    {
        return [];
    }

    /**
     * @param array<FieldLayoutProviderInterface>
     * @param string $fieldHandle
     * @return array<FieldInterface>
     */
    public function getFieldsFromCandidates(array $candidates, string $fieldHandle): array
    {
        $fields = [];

        foreach ($candidates as $candidate) {
            $field = $this->getFieldFromLayout($candidate->getFieldLayout(), $fieldHandle);
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
    protected function getFieldFromLayout(FieldLayout $fieldLayout, string $fieldHandle): ?FieldInterface
    {
        $field = $fieldLayout->getFieldByHandle($fieldHandle);

        if ($field) {
            if (!in_array($field::dbType(), ['json', 'text'])) {
                throw new \InvalidArgumentException("Field is not a JSON or TEXT field: $fieldHandle");
            }
        }
        return $field;
    }
}