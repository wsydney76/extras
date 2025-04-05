# Extras

Extra features for Craft5

This is the software equivalent of tidying up the house: “Don't throw it away, I might still need it! - But it's broken and no longer works properly! - Maybe it does? - Then at least put it all in one box!”

Readme work in progress.

## Requirements

This plugin requires Craft CMS 5.0.0-beta.3 or later, and PHP 8.2 or later.

## Usage

### Twig extensions

Query JSON custom fields, with specific collation

```twig
{% set query = craft.entries
    .section('person')
    .orderBy({
        (field('person.lastName', 'pb').orderBy()): SORT_ASC,
        (field('person.firstName', 'pb').orderBy()): SORT_ASC,
    })
%}
```

```twig
{% set query = craft.entries
    .andWhere(field('lastName', 'pb').equals(term))
%}
```

Specify params for twig files, with type checking, defaults, get query params etc.

```twig
// In include/embed:
{% do params([
    {name: 'entry', class: 'craft\\elements\\Entry', optional: true},
    {name: 'imageUrl', default: ''},
    {name: 'image', class: 'craft\\elements\\Asset', optional: true},
    {name: 'showDate', default: false, type: 'bool'},
    {name: 'showTeaser', default: false, type: 'bool'},
    {name: 'titleStyle', default: '', list: 'lg,strong', allowEmpty: true},
    {name: 'secondaryTitle', default: ''},
    {name: 'secondaryText', default: ''},
    {name: 'imagePos', default: 'right', list: 'right,left'},
    {name: 'anchor', default: ''},
]) %}

// Get from query params with default:
{% do params([
    'entry',
    {name: 'orderBy', get: true, default: 'title'},
    {name: 'filter', get: true, default: 'all' },
], true) %}
```

Easier

```twig
// craft.app.config.custom.facetTypes
{% set facetTypes = option('facetTypes') %}


// craft.app.request.queryParam('filter', 'all')
{% set filter = get('filter', 'all') %}

```

Pedantic localization of German texts

```twig
// Upper 'ß' is not reliably handled by PHP/CSS uppercase
{{ 'Maße'|upperWithSz }} // -> MAẞE

// There is no 'ß' in the Swiss alphabet
{{ 'Maße'|swissText }} // -> Masse 

// Correct number formatting
// https://www.duden.de/sprachwissen/rechtschreibregeln/zahlen-und-ziffern

// No separator in numbers < 10000
{{ 1000|germanNumber }} // -> 1000

// (non-breaking) Space as thousands separator
{{ 1234567.89|germanNumber }} // -> 1 234 567,98


// Country name in addresses, sent from germany to other countries
// https://www.sekretaria.de/bueroorganisation/korrespondenz/geschaeftsbriefe/brief-ins-ausland-adressieren/
// uppercase, only german, french, english allowed

{{ postalCountryName('FR') }} // -> FRANKREICH
{{ postalCountryName('FR', 'es') }} // -> FRANCE

```