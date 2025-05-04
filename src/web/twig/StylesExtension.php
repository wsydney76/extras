<?php

namespace wsydney76\extras\web\twig;

use craft\fields\data\MultiOptionsFieldData;
use craft\helpers\Template;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Twig extension
 */
class StylesExtension extends AbstractExtension
{
    public function getFilters()
    {
        // Define custom Twig filters
        // (see https://twig.symfony.com/doc/3.x/advanced.html#filters)
        return [
            new TwigFilter('classString', [$this, 'classString']),
            new TwigFilter('classAttr', [$this, 'classAttr']),
        ];
    }

    public function classString(MultiOptionsFieldData $fieldData) {
        $classes = [];
        foreach ($fieldData as $class) {
            $classes[] = $class;
        }
        return implode(' ', $classes);
    }

    public function classAttr(MultiOptionsFieldData $fieldData) {
        return Template::raw(sprintf('class="%s"', $this->classString($fieldData)));
    }
}
