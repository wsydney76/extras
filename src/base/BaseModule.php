<?php

/* **************** TODO: FINISH UPGRADE TO CRAFT 5 **************** */

namespace wsydney76\extras\base;

use Craft;
use craft\base\conditions\BaseCondition;
use craft\base\Element;
use craft\base\Model;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\events\DefineAttributeHtmlEvent;
use craft\events\DefineBehaviorsEvent;
use craft\events\DefineRulesEvent;
use craft\events\ElementIndexTableAttributeEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterCpNavItemsEvent;
use craft\events\RegisterElementActionsEvent;
use craft\events\RegisterElementSourcesEvent;
use craft\events\RegisterElementTableAttributesEvent;
use craft\events\RegisterTemplateRootsEvent;
use craft\helpers\Html;
use craft\i18n\PhpMessageSource;
use craft\log\MonologTarget;
use craft\services\Dashboard;
use craft\services\Fields;
use craft\services\Utilities;
use craft\web\twig\variables\Cp;
use craft\web\twig\variables\CraftVariable;
use craft\web\View;
use Monolog\Formatter\LineFormatter;
use Psr\Log\LogLevel;
use wsydney76\extras\base\services\BaseContentService;
use yii\base\Event;
use yii\base\InvalidConfigException;
use yii\base\Module;

/**
 * @property-read BaseContentService $contentService
 */
class BaseModule extends Module
{
    protected string $handle = '';

    public function init(): void
    {
        $this->setAlias();

        $this->setControllerNamespace();

        $this->setComponents([
            'contentService' => BaseContentService::class,
        ]);

        parent::init();
    }

    /**
     * @return void
     */
    protected function setAlias(string $namespace = 'modules'): void
    {
        // Required for php craft help
        Craft::setAlias("@$namespace/" . $this->handle, $this->getBasePath());
    }

    /**
     * @return void
     */
    protected function setControllerNamespace(string $namespace = 'modules'): void
    {

        // Set the controllerNamespace based on whether this is a console or web request
        if (Craft::$app->getRequest()->getIsConsoleRequest()) {
            $this->controllerNamespace = $namespace . '\\' . $this->handle . '\\console\\controllers';
        } else {
            $this->controllerNamespace = $namespace . '\\' . $this->handle . '\\controllers';
        }
    }

    /**
     * @return void
     */
    protected function registerTranslationCategory(): void
    {
        Craft::$app->i18n->translations[$this->handle] = [
            'class' => PhpMessageSource::class,
            'sourceLanguage' => 'en',
            'basePath' => $this->basePath . '/translations',
            'allowOverrides' => true,
        ];
    }

    /**
     * @param bool $site
     * @param bool $cp
     * @return void
     */
    protected function registerTemplateRoots(bool $site = true, bool $cp = true, string $handle = null): void
    {
        if (!$handle) {
            $handle = $this->handle;
        }

        // Base template directory
        if ($site) {
            Event::on(
                View::class,
                View::EVENT_REGISTER_SITE_TEMPLATE_ROOTS, function(RegisterTemplateRootsEvent $event) use ($handle): void {
                $event->roots[$handle] = $this->getBasePath() . DIRECTORY_SEPARATOR . 'templates';
            });
        }

        if ($cp) {
            Event::on(
                View::class,
                View::EVENT_REGISTER_CP_TEMPLATE_ROOTS, function(RegisterTemplateRootsEvent $event) use ($handle): void {
                $event->roots[$handle] = $this->getBasePath() . DIRECTORY_SEPARATOR . 'templates';
            });
        }
    }

    /**
     * @param string $className
     * @param array<string> $behaviors
     * @return void
     */
    protected function registerBehaviors(string $className, array $behaviors): void
    {
        // Register Behaviors
        Event::on(
            $className,
            Model::EVENT_DEFINE_BEHAVIORS,
            function(DefineBehaviorsEvent $event) use ($behaviors): void {
                foreach ($behaviors as $behavior) {
                    $event->behaviors[] = $behavior;
                }
            });
    }

    /**
     * @param array<string> $conditionRuleTypes
     * @return void
     */
    protected function registerConditionRuleTypes(array $conditionRuleTypes): void
    {
        // Register Custom Conditions
        Event::on(
            BaseCondition::class,
            BaseCondition::EVENT_REGISTER_CONDITION_RULES,
            function(\craft\events\RegisterConditionRulesEvent $event) use ($conditionRuleTypes): void {
                foreach ($conditionRuleTypes as $conditionRuleType) {
                    $event->conditionRules[] = $conditionRuleType;
                }
            }
        );
    }

    /**
     * @param array<string> $fieldTypes
     * @return void
     */
    protected function registerFieldTypes(array $fieldTypes): void
    {
        // Register custom field types
        Event::on(
            Fields::class,
            Fields::EVENT_REGISTER_FIELD_TYPES,
            function(RegisterComponentTypesEvent $event) use ($fieldTypes): void {
                foreach ($fieldTypes as $fieldType) {
                    $event->types[] = $fieldType;
                }
            });
    }

    /**
     * @param array<string> $widgetTypes
     * @return void
     */
    protected function registerWidgetTypes(array $widgetTypes): void
    {
        Event::on(
            Dashboard::class,
            Dashboard::EVENT_REGISTER_WIDGET_TYPES,
            function(RegisterComponentTypesEvent $event) use ($widgetTypes) {
                foreach ($widgetTypes as $widgetType) {
                    $event->types[] = $widgetType;
                }
            }
        );
    }

    protected function registerUtilities(array $utilities): void
    {
        Event::on(
            Utilities::class,
            Utilities::EVENT_REGISTER_UTILITIES,
            function(RegisterComponentTypesEvent $event) use ($utilities) {
                foreach ($utilities as $utility) {
                    $event->types[] = $utility;
                }
            }
        );
    }

    /**
     * @param array<string> $extensions
     * @return void
     * @throws InvalidConfigException
     */
    protected function registerTwigExtensions(array $extensions): void
    {
        foreach ($extensions as $extension) {
            /* @phpstan-ignore-next-line */
            Craft::$app->view->registerTwigExtension(Craft::createObject($extension));
        }
    }

    /**
     * @param array<string> $services
     * @return void
     */
    protected function registerServices(array $services): void
    {
        // Register Services
        $this->setComponents($services);
    }

    /**
     * @param array $navItem
     * @param $pos
     * @return void
     */
    protected function registerNavItem(array $navItem, ?int $pos = null): void
    {
        Event::on(
            Cp::class,
            Cp::EVENT_REGISTER_CP_NAV_ITEMS,
            function(RegisterCpNavItemsEvent $event) use ($navItem, $pos) {
                if ($pos) {
                    array_splice($event->navItems, $pos, 0, [$navItem]);
                } else {
                    $event->navItems[] = $navItem;
                }
            }
        );
    }

    /**
     * @param array<string> $assetBundles
     * @return void
     * @throws InvalidConfigException
     */
    protected function registerAssetBundles(array $assetBundles): void
    {
        foreach ($assetBundles as $assetBundle) {
            Craft::$app->view->registerAssetBundle($assetBundle);
        }
    }

    /**
     * @param array $rules
     * @return void
     */
    protected function registerEntryValidators(array $rules): void
    {
        Event::on(
            Entry::class,
            Entry::EVENT_DEFINE_RULES, function(DefineRulesEvent $event) use ($rules) {
            foreach ($rules as $rule) {
                $event->rules[] = $rule;
            }
        });
    }

    /**
     * @param string $elementType
     * @param array<string> $actions
     * @return void
     */
    protected function registerElementActions(string $elementType, array $actions): void
    {
        Event::on(
            $elementType,
            Element::EVENT_REGISTER_ACTIONS,
            function(RegisterElementActionsEvent $event) use ($actions) {
                foreach ($actions as $action) {
                    $event->actions[] = $action;
                }
            }
        );
    }

    /**
     * @param array $services
     * @return void
     */
    protected function registerCraftVariableServices(array $services): void
    {
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function($event) use ($services) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                foreach ($services as $service) {
                    $variable->set($service[0], $service[1]);
                }
            }
        );
    }

    /**
     * @param array<string> $behaviors
     * @return void
     */
    protected function registerCraftVariableBehaviors(array $behaviors): void
    {
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function($event) use ($behaviors) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->attachBehaviors($behaviors);
            }
        );
    }


    /**
     * Define a new column for the entries index, which will display a bigger image
     *
     * @param string $attribute handle of the new column
     * @param string $fieldHandle handle of the field to use for the image
     * @param string $label label of the new column
     * @param array $transform transform to use for the image
     */
    protected function setEntriesIndexImageColumn(string $attribute, string $fieldHandle, string $label, array $transform): void
    {
        // Register table attribute
        Event::on(
            Entry::class,
            Element::EVENT_REGISTER_TABLE_ATTRIBUTES,
            function(RegisterElementTableAttributesEvent $event) use ($attribute, $label) {
                $event->tableAttributes[$attribute] = ['label' => $label];
            });

        // Set element index column content
        Event::on(
            Entry::class,
            Element::EVENT_DEFINE_ATTRIBUTE_HTML,
            function(DefineAttributeHtmlEvent $event) use ($attribute, $fieldHandle, $transform) {
                if ($event->attribute === $attribute) {
                    /** @var Entry $entry */
                    $entry = $event->sender;

                    // Set default html
                    $event->html = '';

                    // Get the image fields query
                    $query = $entry->getFieldValue($fieldHandle);

                    // If the field is in the entries field layout
                    if ($query) {
                        $image = $query->one();
                        if ($image) {
                            $image->setTransform($transform);
                            $event->html = Html::tag('img', '', [
                                'src' => $image->url,
                                'style' => 'border-radius: 3px;',
                                'width' => $image->width,
                                'height' => $image->height,
                                'alt' => $image->alt ?? $image->title,
                                'ondblclick' => "Craft.createElementEditor('craft\\\\elements\\\\Asset', {elementId: {$image->id}, siteId: {$entry->site->id}})",
                            ]);
                        }
                    }

                    // Prevent further processing
                    $event->handled = true;
                }
            });

        // https://github.com/craftcms/cms/issues/14639
        Event::on(
            Entry::class,
            Element::EVENT_DEFINE_INLINE_ATTRIBUTE_INPUT_HTML,
            function(DefineAttributeHtmlEvent $event) use ($attribute) {
                if ($event->attribute === $attribute) {
                    $event->html = $event->sender->getAttributeHtml($attribute);
                    $event->handled = true;
                }
            }
        );

        // Eager load transformed images
        Event::on(
            Entry::class,
            Entry::EVENT_PREP_QUERY_FOR_TABLE_ATTRIBUTE,
            function(ElementIndexTableAttributeEvent $event) use ($attribute, $fieldHandle, $transform) {
                if ($event->attribute === $attribute) {
                    // Eager load the image element including the transform
                    $event->query->andWith(
                        [$fieldHandle, ['withTransforms' => [$transform]]]
                    );
                }
            });
        Event::on(
            Entry::class,
            Element::EVENT_DEFINE_INLINE_ATTRIBUTE_INPUT_HTML, function(DefineAttributeHtmlEvent $event) use ($attribute) {
            if ($event->attribute === $attribute) {
                $event->html = $event->sender->getAttributeHtml($attribute);
                $event->handled = true;
            }
        });
    }

    protected function setAssetIndexImageColumn(string $attribute, ?string $fieldHandle, string $label, array $transform): void
    {
        // Register table attribute
        Event::on(
            Asset::class,
            Element::EVENT_REGISTER_TABLE_ATTRIBUTES,
            function(RegisterElementTableAttributesEvent $event) use ($attribute, $label) {
                $event->tableAttributes[$attribute] = ['label' => $label];
            });

        // Set element index column content
        Event::on(
            Asset::class,
            Element::EVENT_DEFINE_ATTRIBUTE_HTML,
            function(\craft\events\DefineAttributeHtmlEvent $event) use ($attribute, $fieldHandle, $transform) {
                if ($event->attribute === $attribute) {
                    /** @var Asset $asset */
                    $asset = $event->sender;

                    // Set default html
                    $event->html = '';

                    if ($asset) {
                        $event->html = Html::tag('img', '', [
                            'src' => $asset->getUrl($transform),
                            'style' => 'border-radius: 3px;',
                            'width' => $transform['width'] ?? $asset->width,
                            'height' => $transform['height'] ?? $asset->height,
                            'alt' => $asset->altText ?? $asset->title,
                            'ondblclick' => "Craft.createElementEditor('craft\\\\elements\\\\Asset', {elementId: {$asset->id}, siteId: {$asset->site->id}})",
                        ]);
                    }


                    // Prevent further processing
                    $event->handled = true;
                }
            });

            Event::on(
            Asset::class,
            Element::EVENT_DEFINE_INLINE_ATTRIBUTE_INPUT_HTML, function(DefineAttributeHtmlEvent $event) use ($attribute) {
                if ($event->attribute === $attribute) {
                    $event->html = $event->sender->getAttributeHtml($attribute);
                    $event->handled = true;
                }
            });
    }

    protected function registerLogTarget(array $config)
    {
        // Register a custom log target, keeping the format as simple as possible.
        // https://putyourlightson.com/articles/adding-logging-to-craft-plugins-with-monolog

        $config += [
            'level' => LogLevel::INFO,
            'logContext' => false,
            'allowLineBreaks' => false,
            'formatter' => new LineFormatter(
                format: "%datetime% %level_name% %message%\n",
                dateFormat: 'Y-m-d H:i:s'),
        ];

        Craft::getLogger()->dispatcher->targets[] = new MonologTarget($config);
    }

    protected function registerNestedEntriesSourcesForRelation(string $section, string $relatedSection, string $fieldHandle): void
    {
        Event::on(
            Entry::class,
            Element::EVENT_REGISTER_SOURCES,
            function(RegisterElementSourcesEvent $event) use ($section, $relatedSection, $fieldHandle): void {

                $section = Craft::$app->entries->getSectionByHandle($section);

                foreach ($event->sources as &$source) {
                    if (isset($source['key']) && $source['key'] === "section:{$section->uid}") {
                        $source['nested'] = Entry::find()
                            ->section($relatedSection)
                            ->orderBy('title')
                            ->collect()
                            ->map(fn($entry) => [
                                'key' => "nested-{$section->handle}-{$entry->uid}",
                                'label' => $entry->title,
                                'criteria' => [
                                    'sectionId' => $section->id,
                                    'editable' => true,
                                    $fieldHandle => $entry->id
                                ],
                            ]
                            )->toArray();
                    }
                }
            }
        );
    }


    protected function registerNestedEntriesSourcesForDropdown(string $section, string $fieldHandle, bool $showEmpty = false): void
    {
        Event::on(
            Entry::class,
            Element::EVENT_REGISTER_SOURCES,
            function(RegisterElementSourcesEvent $event) use ($section, $fieldHandle, $showEmpty): void {

                $section = Craft::$app->entries->getSectionByHandle($section);
                $field = Craft::$app->fields->getFieldByHandle($fieldHandle);


                foreach ($event->sources as &$source) {
                    if (isset($source['key']) && $source['key'] === "section:{$section->uid}") {
                        $options = collect($field->settings['options'] ?? []);

                        if (!$showEmpty) {
                            $options = $options->filter(fn($option) => $option['value'] !== '');
                        }

                        $source['nested'] = $options
                            ->map(fn($option) => [
                                'key' => "nested-$fieldHandle-{$option['value']}",
                                'label' => $option['label'],
                                'criteria' => [
                                    'sectionId' => $section->id,
                                    'editable' => true,
                                    $fieldHandle => $option['value']
                                ],
                            ]
                            )->toArray();
                    }
                }
            }
        );
    }
}
