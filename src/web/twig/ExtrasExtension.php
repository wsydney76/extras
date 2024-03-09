<?php

namespace wsydney76\extras\web\twig;

use Craft;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\TwigTest;
use wsydney76\extras\ExtrasHelper;
use wsydney76\extras\ExtrasPlugin;

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
