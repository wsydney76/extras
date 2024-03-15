<?php

namespace wsydney76\extras\web\twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use wsydney76\extras\helpers\ExtrasHelper;

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
            new TwigFunction('valueSql', function(string $entryTypeHandle, string $fieldHandle) {
                return ExtrasHelper::getValueSql($entryTypeHandle, $fieldHandle);
            }),
        ];
    }
}
