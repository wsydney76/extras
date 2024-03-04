<?php

namespace wsydney76\extras;

use Craft;
use craft\base\conditions\BaseCondition;
use craft\base\Element;
use craft\base\Event;
use craft\base\Model;
use craft\base\Plugin;
use craft\elements\Entry;
use craft\events\DefineAttributeHtmlEvent;
use craft\events\DefineBehaviorsEvent;
use craft\events\DefineHtmlEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterConditionRulesEvent;
use craft\events\RegisterElementTableAttributesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\services\Dashboard;
use craft\services\UserPermissions;
use craft\web\twig\variables\CraftVariable;
use Illuminate\Support\Collection;
use wsydney76\extras\behaviors\EntryBehavior;
use wsydney76\extras\elements\conditions\AllTypesConditionRule;
use wsydney76\extras\elements\conditions\HasDraftsConditionRule;
use wsydney76\extras\models\Settings;
use wsydney76\extras\services\CompareService;
use wsydney76\extras\services\Elementmap;
use wsydney76\extras\services\ElementmapRenderer;
use wsydney76\extras\variables\ExtrasVariable;
use wsydney76\extras\web\assets\cpassets\CustomCpAsset;
use wsydney76\extras\web\assets\sidebarvisibility\SidebarVisibilityAsset;
use wsydney76\extras\widgets\MyProvisionsalDraftsWidget;
use function setlocale;
use const LC_COLLATE;

/**
 * Extras plugin
 *
 * @method static ExtrasPlugin getInstance()
 * @method Settings getSettings()
 */
class ExtrasPlugin extends Plugin
{
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = true;

    public static function config(): array
    {
        return [
            'components' => [
                'elementmap' => Elementmap::class,
                'renderer' => ElementmapRenderer::class,
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
            $this->initSidebarVisibility();
            $this->initConditionRules();
            $this->initElementMap();
            $this->initCpAssets();
            $this->initOwnerPath();
            $this->initWidgets();
            $this->initDraftHelpers();
            $this->enableCollectionMakros();
        });
    }

    protected function createSettingsModel(): ?Model
    {
        return Craft::createObject(Settings::class);
    }

    protected function settingsHtml(): ?string
    {
        return Craft::$app->view->renderTemplate('_extras/_settings.twig', [
            'plugin' => $this,
            'settings' => $this->getSettings(),
        ]);
    }


    /**
     * @return void
     */
    protected function initSidebarVisibility(): void
    {
        if (Craft::$app->request->isCpRequest && $this->getSettings()->enableSidebarVisibility) {
            $this->view->registerAssetBundle(SidebarVisibilityAsset::class);
        }
    }

    protected function initConditionRules()
    {
        if (Craft::$app->request->isCpRequest && $this->getSettings()->enableConditionRules) {
            Event::on(BaseCondition::class,
                BaseCondition::EVENT_REGISTER_CONDITION_RULES, function(RegisterConditionRulesEvent $event) {
                    $event->conditionRules[] = AllTypesConditionRule::class;
                    $event->conditionRules[] = HasDraftsConditionRule::class;
                });
        }
    }

    protected function initElementmap()
    {
        if (Craft::$app->request->isCpRequest && $this->getSettings()->enableElementmap) {
            $this->elementmap->initElementMap();
        }
    }

    protected function initCpAssets()
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

    protected function initOwnerPath()
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

    private function initEntryBehavior()
    {
        Event::on(
            Entry::class,
            Entry::EVENT_DEFINE_BEHAVIORS,
            function(DefineBehaviorsEvent $event) {
                if (!isset($event->behaviors['extrasBehavior'])) {
                    $event->behaviors['extrasBehavior'] = EntryBehavior::class;
                }
            });
    }

    protected function initExtrasVariable()
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

    protected function initWidgets()
    {
        if (Craft::$app->request->isCpRequest && $this->getSettings()->enableWidgets) {

            $this->initEntryBehavior();

            Event::on(
                Dashboard::class,
                Dashboard::EVENT_REGISTER_WIDGET_TYPES, function(RegisterComponentTypesEvent $event) {
                $event->types[] = MyProvisionsalDraftsWidget::class;
            });
        }
    }

    private function initDraftHelpers()
    {

        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS, function(RegisterUserPermissionsEvent $event) {
            $event->permissions['Extras Plugin'] = [
                'heading' => 'Extras Plugin',
                'permissions' => [
                    'accessPlugin-work' => [
                        'label' => Craft::t('_extras', 'Access Extras Plugin'),
                    ],
                    'viewpeerprovisionaldrafts' => [
                        'label' => Craft::t('_extras', 'View provisional drafts of other users')
                    ],
                    'transferprovisionaldrafts' => [
                        'label' => Craft::t('_extras', 'Transfer other users provisional draft to own account')
                    ]
                ]

            ];
        });

        if (Craft::$app->request->isCpRequest && $this->getSettings()->enableDraftHelpers) {


            Event::on(
                CraftVariable::class,
                CraftVariable::EVENT_INIT,
                function(\yii\base\Event $event) {

                    /** @var CraftVariable $variable */
                    $variable = $event->sender;

                    $variable->set('compare', CompareService::class);
                }
            );


            $this->initEntryBehavior();

            Event::on(
                Element::class,
                Element::EVENT_DEFINE_SIDEBAR_HTML, function(DefineHtmlEvent $event) {
                if ($event->sender instanceof Entry) {
                    $event->html =
                        Craft::$app->view->renderTemplate('_extras/entry_hasdrafts.twig', [
                            'entry' => $event->sender
                        ]) . $event->html;

                    if (!Craft::$app->request->isAjax) {
                        $event->html .= Craft::$app->view->renderTemplate('_extras/draft_hints.twig', [
                            'entry' => $event->sender
                        ]);
                    }
                }
            });

            // Register element index column
            Event::on(
                Entry::class,
                Element::EVENT_REGISTER_TABLE_ATTRIBUTES, function(RegisterElementTableAttributesEvent $event) {
                $event->tableAttributes['hasProvisionalDraft'] = ['label' => Craft::t('_extras', 'Edited')];
            });

            Event::on(
                Entry::class,
                Element::EVENT_DEFINE_ATTRIBUTE_HTML, function(DefineAttributeHtmlEvent $event) {

                if ($event->attribute === 'hasProvisionalDraft') {
                    $event->handled = true;
                    /** @var Entry $entry */
                    $entry = $event->sender;
                    $event->html = '';

                    $query = Entry::find()
                        ->draftOf($entry)
                        ->provisionalDrafts(true)
                        ->site($entry->site)
                        ->anyStatus();

                    $countProvisionalDrafts = $query->count();

                    $query->draftCreator(Craft::$app->user->identity);
                    $hasOwnProvisionalDraft = $query->exists();

                    if ($hasOwnProvisionalDraft) {
                        // $event->html .= '<span class="status active"></span>';
                        $event->html .= Craft::$app->view->renderTemplate('_extras/_drafts_indexcolumn', [
                            'count' => $countProvisionalDrafts
                        ]);
                    }

                    if (Craft::$app->user->identity->can('viewpeerprovisionaldrafts')) {
                        // Workaround because there is no ->draftCreator('not ...)
                        if ($hasOwnProvisionalDraft) {
                            --$countProvisionalDrafts;
                        }
                        if ($countProvisionalDrafts) {
                            // $event->html .= '<span class="status"></span>';

                        }
                    }
                }
            });
        }
    }

    protected function enableCollectionMakros()
    {
        if ($this->getSettings()->enableCollectionMakros) {
            Collection::macro('sortByLocale', function(string $key, bool $descending = false): Collection {
                $oldLocale = setlocale(LC_COLLATE, 0);
                setlocale(LC_COLLATE, str_replace('-', '_', Craft::$app->language));
                $sorted = $this->sortBy($key, SORT_LOCALE_STRING, $descending);
                setlocale(LC_COLLATE, $oldLocale);
                return $sorted;
            });

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


}
