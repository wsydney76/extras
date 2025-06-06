<?php

namespace wsydney76\extras;

use Craft;
use craft\base\conditions\BaseCondition;
use craft\base\Element;
use craft\base\Event;
use craft\base\Model;
use craft\base\NestedElementInterface;
use craft\base\Plugin;
use craft\commerce\elements\Product;
use craft\db\Query;
use craft\elements\db\EntryQuery;
use craft\elements\Entry;
use craft\events\DefineBehaviorsEvent;
use craft\events\DefineElementHtmlEvent;
use craft\events\DefineFieldLayoutElementsEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterConditionRulesEvent;
use craft\events\RegisterElementActionsEvent;
use craft\events\RegisterPreviewTargetsEvent;
use craft\events\RegisterTemplateRootsEvent;
use craft\events\SetElementRouteEvent;
use craft\helpers\Cp;
use craft\models\FieldLayout;
use craft\services\Dashboard;
use craft\services\Fields;
use craft\services\Utilities;
use craft\web\twig\variables\CraftVariable;
use craft\web\View;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Collection;
use wsydney76\extras\behaviors\EntryBehavior;
use wsydney76\extras\behaviors\EntryQueryBehavior;
use wsydney76\extras\elements\actions\CopyMarkdownLink;
use wsydney76\extras\elements\actions\CopyReferenceLinkTag;
use wsydney76\extras\elements\conditions\AllTypesConditionRule;
use wsydney76\extras\elements\conditions\HasDraftsConditionRule;
use wsydney76\extras\elements\conditions\IsEditedConditionRule;
use wsydney76\extras\fieldlayoutelements\IncomingRelationships;
use wsydney76\extras\fieldlayoutelements\Instruction;
use wsydney76\extras\fields\ExtendedLightswitch;
use wsydney76\extras\fields\Properties;
use wsydney76\extras\fields\Styles;
use wsydney76\extras\models\Settings;
use wsydney76\extras\services\ContentService;
use wsydney76\extras\services\DraftPackageService;
use wsydney76\extras\services\DraftsHelper;
use wsydney76\extras\services\Elementmap;
use wsydney76\extras\services\ElementmapRenderer;
use wsydney76\extras\services\UpgradeService;
use wsydney76\extras\services\VideoService;
use wsydney76\extras\utilities\DraftPackageUtility;
use wsydney76\extras\utilities\UpgradeInventory;
use wsydney76\extras\utilities\VolumesInventory;
use wsydney76\extras\variables\ExtrasVariable;
use wsydney76\extras\web\assets\ckeditor\CkeditorPlugins;
use wsydney76\extras\web\assets\cpassets\CustomCpAsset;
use wsydney76\extras\web\assets\sidebarvisibility\SidebarVisibilityAsset;
use wsydney76\extras\web\twig\ExtrasExtension;
use wsydney76\extras\web\twig\StylesExtension;
use wsydney76\extras\widgets\MyProvisionsalDraftsWidget;
use function sprintf;

/**
 * Extras plugin
 *
 * @method static ExtrasPlugin getInstance()
 * @method Settings getSettings()
 * @property-read UpgradeService $upgradeService
 * @property-read Elementmap $elementmap
 * @property-read ElementmapRenderer $renderer
 * @property-read DraftsHelper $draftsHelper
 * @property-read VideoService $video
 * @property-read DraftPackageService $draftPackageService
 */
class ExtrasPlugin extends Plugin
{
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = true;

    public bool $hasReadOnlyCpSettings = true;

    public static function config(): array
    {
        return [
            'components' => [
                'elementmap' => Elementmap::class,
                'renderer' => ElementmapRenderer::class,
                'draftsHelper' => DraftsHelper::class,
                'upgradeService' => UpgradeService::class,
                'video' => VideoService::class,
                'draftPackageService' => DraftPackageService::class
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        setlocale(LC_COLLATE, str_replace('-', '_', Craft::$app->locale->id));

        // Defer most setup tasks until Craft is fully initialized
        Craft::$app->onInit(function() {
            $this->initExtrasVariable();
            $this->initCpAssets();
            $this->initOwnerPath();
            $this->draftsHelper->createPermissions();
            $this->initCollectionMakros();
            $this->initTwigExtension();
            $this->setEntryRoute();
            $this->initFields();

            if (Craft::$app->request->isCpRequest) {
                $this->initSidebarVisibility();
                $this->initConditionRules();
                $this->initElementMap();
                $this->initWidgets();
                $this->initDraftHelpers();
                $this->initElementActions();
                $this->initRestoreDismissedTips();
                $this->initCrossSiteValidation();
                $this->initFieldLayoutElements();
                $this->initUtilities();
                $this->initPreviewTargets();
                $this->initCkeditorPlugins();
            } else {
                $this->registerSiteTemplateRoot();
            }
        });
    }

    protected function createSettingsModel(): ?Model
    {
        return Craft::createObject(Settings::class);
    }

    public function getSettingsResponse(): mixed
    {
        // using the settingsHtml() method alone does not allow tabs

        $settingsHtml = Craft::$app->getView()->namespaceInputs(function() {
            return (string)$this->settingsHtml();
        }, 'settings', false);

        return Craft::$app->controller->renderTemplate('_extras/_settings_layout.twig', [
            'plugin' => $this,
            'settingsHtml' => $settingsHtml
        ]);
    }

    protected function settingsHtml(): ?string
    {
        return Craft::$app->view->renderTemplate('_extras/_settings', [
            'settings' => $this->getSettings(),
            'config' => Craft::$app->getConfig()->getConfigFromFile('_extras')
        ]);
    }

    /**
     * @return void
     */
    private function initSidebarVisibility(): void
    {
        if ($this->getSettings()->enableSidebarVisibility) {
            $this->view->registerAssetBundle(SidebarVisibilityAsset::class);
        }
    }

    private function initConditionRules(): void
    {
        if ($this->getSettings()->enableConditionRules) {
            Event::on(BaseCondition::class,
                BaseCondition::EVENT_REGISTER_CONDITION_RULES, function(RegisterConditionRulesEvent $event) {
                    $event->conditionRules[] = AllTypesConditionRule::class;
                    $event->conditionRules[] = HasDraftsConditionRule::class;
                    $event->conditionRules[] = IsEditedConditionRule::class;
                });
        }
    }

    private function initElementmap(): void
    {
        if ($this->getSettings()->enableElementmap) {
            $this->elementmap->initElementMap();
        }
    }

    private function initCpAssets(): void
    {
        if (Craft::$app->request->isCpRequest) {

            $bodyFontSize = $this->getSettings()->bodyFontSize;
            $userBodyFontSize = Craft::$app->user->identity->extrasBodyFontSize ?? '';

            if ($userBodyFontSize) {
                $bodyFontSize = $userBodyFontSize;
            }

            if ($bodyFontSize) {
                Craft::$app->view->registerCss("html, body {font-size: {$bodyFontSize};}");
            }

            $css = trim($this->getSettings()->customCss);
            if ($css) {
                Craft::$app->view->registerCss($css);
            }

            if ($this->getSettings()->enableCpAssets) {
                Craft::$app->view->registerAssetBundle(CustomCpAsset::class);
            }
        }
    }

    private function initOwnerPath(): void
    {
        $this->initEntryBehavior();
        Event::on(
            Entry::class,
            Entry::EVENT_REGISTER_TABLE_ATTRIBUTES,
            function($event) {
                $event->tableAttributes['ownerPath'] = ['label' => Craft::t('_extras', 'Owner Path')];
            }
        );

        Event::on(
            Entry::class,
            Entry::EVENT_DEFINE_ATTRIBUTE_HTML,
            function($event) {
                if ($event->attribute === 'ownerPath') {
                    $event->html = Craft::$app->view->renderTemplate('_extras/_ownerpath_indexcolumn', ['entry' => $event->sender]);
                    $event->handled = true;
                }
            }
        );
    }

    private function initEntryBehavior(): void
    {
        Event::on(
            Entry::class,
            Model::EVENT_DEFINE_BEHAVIORS,
            function(DefineBehaviorsEvent $event) {
                if (!isset($event->behaviors['extrasBehavior'])) {
                    $event->behaviors['extrasBehavior'] = EntryBehavior::class;
                }
            });

        if (Craft::$app->plugins->isPluginEnabled('commerce')) {
            Event::on(
                Product::class,
                Model::EVENT_DEFINE_BEHAVIORS,
                function(DefineBehaviorsEvent $event) {
                    if (!isset($event->behaviors['extrasBehavior'])) {
                        $event->behaviors['extrasBehavior'] = EntryBehavior::class;
                    }
                });
        }
    }

    private function initExtrasVariable(): void
    {
        if ($this->getSettings()->enableExtrasVariable) {
            Event::on(
                CraftVariable::class,
                CraftVariable::EVENT_INIT,
                function(\yii\base\Event $event) {

                    /** @var CraftVariable $variable */
                    $variable = $event->sender;

                    $variable->set('_extras', ExtrasVariable::class);
                }
            );
        }
    }

    private function initWidgets(): void
    {
        if ($this->getSettings()->enableWidgets) {

            $this->initEntryBehavior();

            Event::on(
                Dashboard::class,
                Dashboard::EVENT_REGISTER_WIDGET_TYPES, function(RegisterComponentTypesEvent $event) {
                $event->types[] = MyProvisionsalDraftsWidget::class;
            });
        }
    }

    private function initDraftHelpers(): void
    {
        if ($this->getSettings()->enableDraftHelpers) {
            $this->initEntryBehavior();
            $this->draftsHelper->initDraftsHelper();
        }
    }

    private function initCollectionMakros(): void
    {
        if ($this->getSettings()->enableCollectionMakros) {
            Collection::macro('addToCollection', function(string $key, mixed $value) {
                if ($this->has($key)) {
                    /** @phpstan-ignore-next-line */
                    $this->put($key, $this->get($key)->push($value));
                } else {
                    /** @phpstan-ignore-next-line */
                    $this->put($key, new Collection([$value]));
                }
                return $this;
            });
        }
    }

    private function initElementActions(): void
    {
        if ($this->getSettings()->enableElementActions) {
            Event::on(
                Entry::class,
                Entry::EVENT_REGISTER_ACTIONS,
                function(RegisterElementActionsEvent $event) {
                    $event->actions[] = CopyMarkdownLink::class;
                    $event->actions[] = CopyReferenceLinkTag::class;
                }
            );
        }

        if ($this->getSettings()->enableViewLinkInCards) {
            Event::on(
                Cp::class,
                Cp::EVENT_DEFINE_ELEMENT_CARD_HTML,
                function(DefineElementHtmlEvent $event) {
                    if ((
                            ($event->element instanceof Entry && $event->element->section) ||
                            $event->element instanceof Product
                        ) &&
                        $event->element->url) {

                        $viewLinkHtml = sprintf('<div style="padding-right: 10px; padding-top:1.5px;"><a href="%s" class="go" title="%s" target="_blank"></a></div>',
                            $event->element->url,
                            Craft::t('_extras', 'View')
                        );

                        $event->html = $this->insertViewLink($event->html, $viewLinkHtml);
                    }
                }
            );
        }
    }

    private function initTwigExtension()
    {
        if ($this->getSettings()->enableTwigExtension) {
            Craft::$app->view->registerTwigExtension(new ExtrasExtension());
        }
    }

    private function initRestoreDismissedTips()
    {
        if ($this->getSettings()->enableRestoreDismissedTips) {
            Craft::$app->view->hook('cp.users.edit.prefs', function(array &$context) {
                return Craft::$app->view->renderTemplate('_extras/cp-dismissed-tips.twig');
            });
        }
    }

    private function initFields()
    {
        if ($this->getSettings()->enableExtendedLightswitchField) {
            Event::on(
                Fields::class,
                Fields::EVENT_REGISTER_FIELD_TYPES,
                function(RegisterComponentTypesEvent $event) {
                    $event->types[] = ExtendedLightswitch::class;
                });
        }

        if ($this->getSettings()->enableStylesField) {
            Event::on(
                Fields::class,
                Fields::EVENT_REGISTER_FIELD_TYPES,
                function(RegisterComponentTypesEvent $event) {
                    $event->types[] = Styles::class;
                });
            Craft::$app->view->registerTwigExtension(new StylesExtension());
        }
    }

    private function initCkeditorPlugins()
    {
        if ($this->getSettings()->enableCkeditorPlugins && Craft::$app->plugins->isPluginEnabled('ckeditor')) {
            \craft\ckeditor\Plugin::registerCkeditorPackage(CkeditorPlugins::class);
        }
    }


    private array $validationIds = [];

    private function initCrossSiteValidation(): void
    {
        if ($this->getSettings()->enableCrossSiteValidation && Craft::$app->isMultiSite) {
            Event::on(
                Entry::class,
                Entry::EVENT_BEFORE_VALIDATE,
                function($event) {
                    /** @var Entry $entry */
                    $entry = $event->sender;
                    Craft::error('Cross site validation start ' . $entry->id . ' ' . $entry->siteId . ' ' . $entry->type->handle, __METHOD__);


                    // Don't loop infinitely
                    if (in_array($entry->id, $this->validationIds, true)) {
                        return;
                    }
                    $this->validationIds[] = $entry->id;

                    if ($entry->scenario !== Element::SCENARIO_LIVE) {
                        return;
                    }

                    $entry->validate();
                    if ($entry->hasErrors()) {
                        return;
                    }

                    Craft::error('Cross site validation check ' . $entry->id . ' ' . $entry->siteId . ' ' . $entry->type->handle, __METHOD__);
                    $event->isValid = $this->_validateLocalizedElements($entry);
                }
            );
        }
    }

    private function _validateLocalizedElements(Element $element): bool
    {

        foreach ($element->getLocalized()->all() as $localizedElement) {
            $localizedElement->scenario = Element::SCENARIO_LIVE;

            if (!$localizedElement->validate()) {
                $element->addError(
                // $element->type->hasTitleField ? 'title' : 'slug',
                    'localizedElement',
                    Craft::t('site', 'Error validating in site') .
                    ' "' . $localizedElement->site->name . '". ' .
                    implode(' ', $localizedElement->getErrorSummary(false)));
                return false;
            }
        }

        return true;
    }

    private function initFieldLayoutElements()
    {
        if ($this->getSettings()->enableFieldLayoutElements) {
            Event::on(
                FieldLayout::class,
                FieldLayout::EVENT_DEFINE_UI_ELEMENTS,
                function(DefineFieldLayoutElementsEvent $event) {
                    $event->elements[] = new Instruction();
                }
            );
        }
    }

    /**
     * @return void
     */
    protected function initUtilities(): void
    {
        if ($this->getSettings()->enableVolumeInventory) {
            Event::on(
                Utilities::class,
                Utilities::EVENT_REGISTER_UTILITIES,
                function(RegisterComponentTypesEvent $event) {
                    $event->types[] = VolumesInventory::class;
                });
        }

        if ($this->getSettings()->enableUpgradeInventory) {
            Event::on(
                Utilities::class,
                Utilities::EVENT_REGISTER_UTILITIES,
                function(RegisterComponentTypesEvent $event) {
                    $event->types[] = UpgradeInventory::class;
                });
        }

        if ($this->getSettings()->enableDraftPackageUtility && Craft::$app->user->identity && Craft::$app->user->identity->admin) {
            Event::on(
                Utilities::class,
                Utilities::EVENT_REGISTER_UTILITIES,
                function(RegisterComponentTypesEvent $event) {
                    $event->types[] = DraftPackageUtility::class;
                });
        }
    }


    /**
     * @return void
     */
    private function setEntryRoute(): void
    {
        if ($this->getSettings()->enableActionRoutes) {

            Event::on(
                Entry::class,
                Element::EVENT_SET_ROUTE,
                function(SetElementRouteEvent $event) {
                    /** @var Entry $entry */
                    $entry = $event->sender;

                    $template = $entry->section ?
                        // craft\elements\Entry::route()
                        $entry->section->getSiteSettings()[$entry->siteId]->template ?? null :
                        // craft\fields\Matrix::getRouteForElement()
                        $entry->field->siteSettings[$entry->site->uid]['template'] ?? '';

                    if (!$template) {
                        return;
                    }

                    if (str_starts_with($template, 'action:')) {
                        // Assume the setting is correct, will throw an error anyway if not
                        $action = explode(':', $template)[1];
                        $event->route = $action;
                        $event->handled = true;
                    }
                });
        }
    }

    private function insertViewLink($givenHtml, $htmlContent): false|string
    {

        // Load the given HTML content into a DOMDocument object
        $dom = new DOMDocument();
        // Load HTML with proper encoding handling
        @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $givenHtml);

        // Find the target div to append the new content
        // TODO: This does not work for singles
        $xpath = new DOMXPath($dom);
        $cardTargetDiv = $xpath->query("//div[contains(@class, 'card-actions')]")->item(0);


        if ($cardTargetDiv) {
            // Create a new fragment for custom content
            $fragment = $dom->createDocumentFragment();
            $fragment->appendXML($htmlContent);

            // Prepend the new content as the first child of the target div
            $cardTargetDiv->insertBefore($fragment, $cardTargetDiv->firstChild);

            // Save and return the modified HTML
            return $dom->saveHTML();
        }

        return $givenHtml; // Return original HTML if card-actions not found
    }

    private function registerSiteTemplateRoot()
    {
        Event::on(
            View::class,
            View::EVENT_REGISTER_SITE_TEMPLATE_ROOTS,
            function(RegisterTemplateRootsEvent $event) {
                $event->roots['@extras'] = __DIR__ . '/templates';
            }
        );
    }

    private function initPreviewTargets()
    {
        $enableInspectPreviewTarget = $this->getSettings()->enableInspectPreviewTarget;
        if (
            $enableInspectPreviewTarget === 'always' ||
            ($enableInspectPreviewTarget === 'devMode' && Craft::$app->config->general->devMode)
        ) {
            Event::on(
                Entry::class,
                Entry::EVENT_REGISTER_PREVIEW_TARGETS,
                function(RegisterPreviewTargetsEvent $event) {
                    $event->previewTargets[] = [
                        'label' => 'Inspect',
                        'refresh' => 1,
                        'urlFormat' => '@extras/inspect?id={id}'
                    ];
                }
            );
        }
    }
}
