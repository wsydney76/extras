<?php

namespace wsydney76\extras\web\twig;

use craft\base\FieldLayoutProviderInterface;
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
            new TwigFunction('orderByJsonColumn', [$this, 'orderByJsonColumn']),
            new TwigFunction('equalsLower', [$this, 'equalsLower']),
        ];
    }

    /**
     * Creates a SQL condition for ordering by a JSON column with custom collation.
     *
     * @param string|FieldLayoutProviderInterface $provider
     * @param string $fieldHandle
     * @param string $collation
     * @param string|null $key
     * @return string
     */
    public function orderByJsonColumn(string|FieldLayoutProviderInterface $provider, string $fieldHandle, string $collation = 'ci', ?string $key = null): string
    {
        return ExtrasHelper::orderByJsonColumn($provider, $fieldHandle, $collation, $key);
    }

    /**
     * Creates a SQL condition for comparing a JSON column in lower case.
     *
     * @param string|FieldLayoutProviderInterface $provider
     * @param string $fieldHandle
     * @param string $term
     * @param string|null $key
     * @return string
     */
    public function equalsLower(string|FieldLayoutProviderInterface $provider, string $fieldHandle, string $term = '', ?string $key = null): string
    {
        return ExtrasHelper::equalsLower($provider, $fieldHandle, $term, $key);
    }

}
