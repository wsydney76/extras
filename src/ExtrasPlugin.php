<?php

namespace wsydney76\extras;

use Craft;
use craft\base\conditions\BaseCondition;
use craft\base\Element;
use craft\base\Event;
use craft\base\Model;
use craft\base\Plugin;
use craft\elements\Entry;
use craft\events\DefineBehaviorsEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterConditionRulesEvent;
use craft\events\RegisterElementActionsEvent;
use craft\services\Dashboard;
use craft\web\twig\variables\CraftVariable;
use Illuminate\Support\Collection;
use wsydney76\extras\behaviors\EntryBehavior;
use wsydney76\extras\elements\actions\CopyMarkdownLink;
use wsydney76\extras\elements\actions\CopyReferenceLinkTag;
use wsydney76\extras\elements\conditions\AllTypesConditionRule;
use wsydney76\extras\elements\conditions\HasDraftsConditionRule;
use wsydney76\extras\models\Settings;
use wsydney76\extras\services\DraftsHelper;
use wsydney76\extras\services\Elementmap;
use wsydney76\extras\services\ElementmapRenderer;
use wsydney76\extras\variables\ExtrasVariable;
use wsydney76\extras\web\assets\cpassets\CustomCpAsset;
use wsydney76\extras\web\assets\sidebarvisibility\SidebarVisibilityAsset;
use wsydney76\extras\widgets\MyProvisionsalDraftsWidget;

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
                'draftsHelper' => DraftsHelper::class
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
            $this->initCollectionMakros();

            Event::on(
                Entry::class,
                Element::EVENT_REGISTER_ACTIONS,
                function(RegisterElementActionsEvent $event) {
                    $event->actions[] = CopyMarkdownLink::class;
                    $event->actions[] = CopyReferenceLinkTag::class;
                }
            );

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
        $this->draftsHelper->createPermissions();
        if (Craft::$app->request->isCpRequest && $this->getSettings()->enableDraftHelpers) {
            $this->initEntryBehavior();
            $this->draftsHelper->initDraftsHelper();
        }
    }

    protected function initCollectionMakros()
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


}
