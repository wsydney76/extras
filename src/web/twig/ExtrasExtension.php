<?php

namespace wsydney76\extras\web\twig;

use CommerceGuys\Addressing\Country\Country;
use CommerceGuys\Addressing\Exception\UnknownCountryException;
use Craft;
use craft\errors\SiteNotFoundException;
use craft\web\twig\Environment;
use Exception;
use InvalidArgumentException;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;
use wsydney76\extras\models\JsonCustomField;
use yii\web\BadRequestHttpException;
use function in_array;
use function mb_strtoupper;
use function str_starts_with;

/**
 * Twig extension
 */
class ExtrasExtension extends AbstractExtension

{
    public function getFunctions(): array
    {
        // Define custom Twig functions
        // (see https://twig.symfony.com/doc/3.x/advanced.html#functions)
        return [
            new TwigFunction('field', $this->jsonCustomField(...)),
            new TwigFunction('get', $this->get(...), ['needs_context' => true]),
            new TwigFunction('getRequired', $this->getRequired(...)),
            new TwigFunction('option', $this->option(...)),
            new TwigFunction('flash', $this->flash(...)),
            new TwigFunction('microtime', $this->microtime(...)),
            new TwigFunction('param', $this->param(...), ['needs_context' => true]),
            new TwigFunction('params', $this->params(...), ['needs_context' => true]),
            new TwigFunction('extraParams', $this->extraParams(...), ['needs_environment' => true, 'needs_context' => true]),
            new TwigFunction('country', $this->country(...)),
            new TwigFunction('postalCountryName', $this->postalCountryName(...)),
        ];
    }

    public function getFilters()
    {
        return [
            new TwigFilter('upperWithSz', $this->upperWithSz(...)),
            new TwigFilter('swissText', $this->swissText(...)),
            new TwigFilter('germanNumber', $this->germanNumber(...), ['is_safe' => ['html']]),
        ];
    }



    /* ========================================================================== */
    /* = Twig functions                                                         = */
    /* ========================================================================== */

    /**
     * @throws Exception
     */
    public function jsonCustomField(string $fieldIdent, string $collation = 'ci'): JsonCustomField
    {
        return new JsonCustomField($fieldIdent, $collation);
    }


    public function get(&$context, ?string $name = null, mixed $defaultValue = null): mixed
    {
        if ($name === null) {
            $queryParams = Craft::$app->request->getQueryParams();
            foreach ($queryParams as $key => $queryParam) {
                $context[$key] = $queryParam;
            }
        }

        return Craft::$app->request->getQueryParam($name, $defaultValue);
    }

    /**
     * @throws BadRequestHttpException
     */
    public function getRequired(string $name): mixed
    {
        return Craft::$app->request->getRequiredQueryParam($name);
    }

    public function option(?string $name = null, mixed $defaultValue = null): mixed
    {
        if ($name === null) {
            return Craft::$app->config->custom;
        }

        try {
            return Craft::$app->config->custom->$name;
        } catch (Exception $e) {
            if ($defaultValue === null) {
                throw new \InvalidArgumentException('Option not found: ' . $name);
            }
            return $defaultValue;
        }
    }

    public function flash(string $name, $defaultValue = null): mixed
    {
        return Craft::$app->session->getFlash($name, $defaultValue);
    }

    public function microtime(): float
    {
        return microtime(true);
    }

    public function params(&$context, array $params, $check = false): void
    {
        foreach ($params as $param) {
            $this->param($context, $param);
        }

        if ($check) {
            $moreParams = array_map(function($param) {
                return is_string($param) ? $param : $param['name'];
            }, $params);
            $invalidParams = $this->extraParams(Craft::$app->view->twig, $context, $moreParams);
            if ($invalidParams) {
                throw new \InvalidArgumentException('Extra params: ' . $invalidParams);
            }
        }
    }

    /**
     * Retrieves and validates a parameter from the given context.
     *
     * This function supports various options to define the expected type, default value,
     * and whether the parameter is optional or should be fetched from a request.
     *
     * @param array &$context The context array where the parameter should be retrieved from or set to.
     * @param array|string $options An array of options or a string specifying the parameter name.
     *                              Available options:
     *                              - 'name' (string): The name of the parameter (required).
     *                              - 'default' (mixed): The default value if the parameter is not set (default: null).
     *                              - 'type' (string|null): The expected type of the parameter (default: null).
     *                              - 'cast' (string|null): Cast the parameter to a specific type (int, float, bool) (default: null).
     *                              - 'class' (string|null): The expected class if 'type' is 'class' (default: null).
     *                              - 'list' (array|string|null): A list of acceptable values if 'type' is 'list' (default: null).
     *                              - 'get' (bool): Whether to fetch the parameter from the request (default: false).
     *                              - 'optional' (bool): Whether the parameter is optional (default: false).
     *                              - 'allowEmpty' (bool): Whether empty values are allowed (default: false).
     * @return mixed The validated parameter value.
     * @throws \InvalidArgumentException If required parameters are missing or validation fails.
     */
    public function param(array &$context, array|string $options = []): mixed
    {

        if (is_string($options)) {
            $options = ['name' => $options];
        }

        $options = array_merge([
            'default' => null,
            'type' => null,
            'class' => null,
            'list' => null,
            'get' => false,
            'optional' => false,
            'allowEmpty' => false,
            'cast' => null,
        ], $options);

        if (!isset($options['name'])) {
            throw new \InvalidArgumentException('Param name is required');
        }

        $key = $options['name'];

        if (isset($options['class'])) {
            $options['type'] = 'class';
        } elseif (isset($options['list'])) {
            $options['type'] = 'list';
        }

        if ($options['get']) {
            $param = Craft::$app->request->getParam($key);
            if ($param !== null) {
                $context[$key] = $param;
            }
        }


        if (!isset($context[$key])) {

            if ($options['optional']) {
                return null;
            }

            if ($options['default'] === null) {
                throw new \InvalidArgumentException('Required param ' . $key . ' missing. Either a parameter must be passed or a default value must be defined.');
            }

            // Modify the context
            $context[$key] = $options['default'];
        }


        if ($options['type'] && (!in_array($options['type'], ['int', 'float', 'array', 'bool', 'class', 'list']))) {
            throw new \InvalidArgumentException('Invalid type: ' . $options['type']);
        }

        // TODO: Allow for multiple types

        if ($options['list'] && is_string($options['list'])) {
            $options['list'] = explode(',', $options['list']);
        }

        $context[$key] = match ($options['cast']) {
            'int' => (int)$context[$key],
            'float' => (float)$context[$key],
            'bool' => (bool)$context[$key],
            default => $context[$key],
        };

        $value = $context[$key];


        $isValid = match ($options['type']) {
            'int' => is_scalar($value) && preg_match('/^\d+$/', $value), // is_int($value) does not work for request parameters
            'float' => is_float($value),
            'array' => is_array($value),
            'bool' => is_bool($value),
            'class' => $value instanceof $options['class'],
            'list' => ($value === '' && $options['allowEmpty']) || in_array($value, $options['list'], true),
            default => true,
        };

        if (!$isValid) {
            $valueDisplay = is_scalar($value) ? ('"' . $value . '"') : '(complex value)';
            throw new \InvalidArgumentException("Param $key $valueDisplay is not of type {$options['type']}.");
        }

        return $value;
    }

    /**
     * For debugging purposes, return a list of all non default variables in the context
     * These are the variables that should be covered by param(s) statement
     *
     * @param array $context
     * @return string
     */
    public function extraParams(Environment $env, array &$context, $moreParams = []): string
    {

        $allParams = array_merge(array_keys($env->getGlobals()), $moreParams);

        $extraParams = array_diff(array_keys($context), $allParams,);

        $extraParams = array_filter($extraParams, function($key) use ($context) {
            return $key !== 'variables' && !str_starts_with($key, 'global_');
        });

        return implode(',', $extraParams);
    }


    /* ========================================================================== */
    /* = Twig filters                                                           = */
    /* ========================================================================== */

    public function upperWithSz(string $text): string
    {
        return mb_strtoupper(str_replace('ß', 'ẞ', $text), 'UTF-8');
    }

    public function swissText(string $text): string
    {
        return str_replace('ß', 'ss', $text);
    }

    /**
     * @param string $countryCode
     * @return Country
     * @throws SiteNotFoundException
     * @throws UnknownCountryException
     */
    public function country(string $countryCode): Country
    {
        return Craft::$app->getAddresses()->countryRepository->get(
            $countryCode,
            Craft::$app->getSites()->getCurrentSite()->language
        );

    }


    /**
     * Retrieve country name for postal address according to
     * https://www.deutschepost.de/de/b/briefe-ins-ausland/laenderkuerzel-laendercode.html
     *
     * @param string $countryCode
     * @return string
     * @throws SiteNotFoundException
     * @throws UnknownCountryException
     */
    public function postalCountryName(string $countryCode, ?string $locale = null): string
    {
        if (!$locale) {
            $locale = Craft::$app->getSites()->getCurrentSite()->language;
        }

        $localeCountry = explode('-', $locale)[0];

        // Country name is allowed in German, French and English
        if (!in_array($localeCountry, ['de','fr','en']) ) {
            $locale = 'en-US';
        }

        $country = Craft::$app->getAddresses()->countryRepository->get($countryCode, $locale);

        return mb_strtoupper($country->getName(), 'UTF-8');
    }

    public function germanNumber(mixed $value, ?int $decimals = null, array $options = [], array $textOptions = []): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        try {
            $formattedValue = Craft::$app->getFormatter()->asDecimal($value, $decimals, $options, $textOptions);
            if (Craft::$app->locale->id === 'de-DE') {
               if ($value >= 10000) {
                   // &#x202F; would be correct, using &nbsp; for better compatibility
                   $formattedValue = str_replace('.', '&nbsp;', $formattedValue);
               } else {
                   $formattedValue = str_replace('.', '', $formattedValue);
               }
            }
        } catch (InvalidArgumentException) {
            throw new InvalidArgumentException('Invalid number format');
        }

        return $formattedValue;
    }
}
