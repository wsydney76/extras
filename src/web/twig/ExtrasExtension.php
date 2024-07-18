<?php

namespace wsydney76\extras\web\twig;

use craft\base\FieldLayoutProviderInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use wsydney76\extras\helpers\ExtrasHelper;
use wsydney76\extras\models\JsonColumn;

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
            new TwigFunction('jsonColumn', [$this, 'jsonColumn']),
        ];
    }

    public function jsonColumn(string $fieldIdent, string $collation = 'ci'): JsonColumn
    {
        return new JsonColumn($fieldIdent, $collation);
    }

}
