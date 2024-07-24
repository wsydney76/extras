<?php

namespace wsydney76\extras\web\twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use wsydney76\extras\helpers\JsonCustomFieldHelper;
use wsydney76\extras\models\JsonCustomField;

/**
 * Twig extension
 */
class ExtrasExtension extends AbstractExtension
{
    public function getFunctions()
    {
        // Define custom Twig functions
        // (see https://twig.symfony.com/doc/3.x/advanced.html#functions)
        return [
            new TwigFunction('field', $this->jsonCustomField(...)),
            new TwigFunction('jsonColumnHelper', $this->jsonCustomFieldHelper(...)),
        ];
    }

    public function jsonCustomField(string $fieldIdent, string $collation = 'ci'): JsonCustomField
    {
        return new JsonCustomField($fieldIdent, $collation);
    }

    public function jsonCustomFieldHelper(): JsonCustomFieldHelper
    {
        return new JsonCustomFieldHelper();
    }

}
