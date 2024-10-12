<?php

/* **************** TODO: UPGRADE TO CRAFT 5 **************** */

namespace wsydney76\extras\base\services;

use Craft;
use craft\base\Field;
use craft\base\FieldInterface;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\errors\BusyResourceException;
use craft\errors\EntryTypeNotFoundException;
use craft\errors\SectionNotFoundException;
use craft\errors\StaleResourceException;
use craft\fieldlayoutelements\CustomField;
use craft\fieldlayoutelements\LineBreak;
use craft\fieldlayoutelements\Tip;
use craft\fieldlayoutelements\TitleField;
use craft\fields\Entries;
use craft\fields\Matrix;
use craft\models\FieldLayoutTab;
use craft\models\MatrixBlockType;
use craft\models\Section;
use craft\models\Section_SiteSettings;
use craft\models\Site;
use craft\records\FieldGroup as FieldGroupRecord;
use Faker\Generator;
use Throwable;
use wsydney76\extras\base\BaseModule;
use yii\base\ErrorException;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\base\NotSupportedException;
use yii\web\ServerErrorHttpException;
use function collect;
use function in_array;
use function is_string;
use function strtolower;

/**
 * Base Migration Service
 *
 * This includes some methods that plugins can use to install their own information model.
 */
class BaseMigrationService extends BaseService
{
    protected string $logCategory = 'migration';
    protected string $imageVolume = 'images';
    protected int $minHeroImageWidth = 1900;
    
    protected string $translationCategory = 'site';
    protected string $templateRoot = 'base';

    /** @var array<Section> $sections */
    protected array $sections = [];

    /** @var array<FieldInterface> $fields */
    protected array $fields = [];

    protected FieldGroupRecord $fieldGroup;

    /** @var array<string> $doUpdateFieldlayout */
    protected array $doUpdateFieldlayout = [];

    protected Generator $faker;


    /**
     * @param array $config
     * @return bool
     * @throws InvalidConfigException
     * @throws Throwable
     * @throws EntryTypeNotFoundException
     * @throws SectionNotFoundException
     */
    protected function createSection(array $config): bool
    {
        $name = $config['name'];
        $type = $config['type'] ?? Section::TYPE_CHANNEL;
        $handle = $config['handle'] ?? strtolower($config['name']);
        $plural = $config['plural'] ?? $config['name'];
        $baseUri = $config['baseUri'] ?? strtolower($plural);
        $titleFormat = $config['titleFormat'] ?? '';
        $addIndexPage = $config['addIndexPage'] ?? false;
        $withHeroFields = $config['withHeroFields'] ?? false;
        $createEntriesField = $config['createEntriesField'] ?? false;
        $template = $config['template'] ?? "$this->templateRoot/sections/$handle";
        $hasUrls = isset($config['hasUrls']) ? $config['hasUrls'] : true;

        $this->sections[$handle] = Craft::$app->entries->getSectionByHandle($handle);
        if ($this->sections[$handle]) {
            return true;
        }

        $section = new Section([
                'name' => $name,
                'handle' => $handle,
                'type' => $type,
                'siteSettings' => collect(Craft::$app->sites->getAllSites())
                    ->map(fn(Site $site) => new Section_SiteSettings([
                        'siteId' => $site->id,
                        'enabledByDefault' => true,
                        'hasUrls' => $hasUrls,
                        'uriFormat' => $hasUrls ? Craft::t($this->translationCategory, $baseUri, language: $site->language) . '/{slug}' : '',
                        'template' => $hasUrls ? $template : '',
                    ]))
                    ->toArray(),
            ]
        );

        if (!Craft::$app->entries->saveSection($section)) {
            $this->error("Could not create section $handle: {$section}");
            return false;
        }

        $this->info("Section $name created.");

        $this->doUpdateFieldlayout[] = $section->handle;

        $type = $section->getEntryTypes()[0];
        $type->titleTranslationMethod = Field::TRANSLATION_METHOD_LANGUAGE;
        if ($titleFormat) {
            $type->hasTitleField = false;
            $type->titleFormat = $titleFormat;
        }

        if (!Craft::$app->entries->saveEntryType($type)) {
            $this->error("Could not save entry type for $handle");
        }

        $this->info("Entry type $section->name/$type->name updated.");

        if ($addIndexPage) {
            $homePage = Entry::findOne(['slug' => '__home__']);

            $fields = [
                'pageTemplate' => "$this->templateRoot/_entries/$section->handle/index",
            ];

            $contentService = BaseModule::getInstance()->contentService;

            if ($withHeroFields) {
                $image = $this->getRandomImage($this->minHeroImageWidth);
                $fields['featuredImage'] = $image ? [$image->id] : null;
                $fields['tagline'] = $this->faker->text(40);
            }

            $contentService->createEntry([
                'section' => 'page',
                'type' => 'pageTemplate',
                'title' => $plural,
                'slug' => $baseUri,
                'parent' => $homePage,
                'fields' => $fields,
                'localized' => [
                    'de' => [
                        'title' => Craft::t($this->translationCategory, $plural, language: 'de_DE'),
                        'slug' => Craft::t($this->translationCategory, $baseUri, language: 'de_DE'),
                    ],
                ],
            ]);
        }

        if ($createEntriesField) {
            $this->createField([
                'class' => Entries::class,
                'groupId' => $this->fieldGroup->id,
                'name' => $plural,
                'handle' => $config['entriesFieldHandle'] ?? $baseUri,
                'sources' => [
                    "section:$section->uid",
                ],
            ]);
        }

        $this->sections[$handle] = $section;
        return true;
    }

    /**
     * @param array $config
     * @return bool
     * @throws InvalidConfigException
     * @throws Throwable
     */
    protected function createField(array $config): bool
    {
        $handle = $config['handle'];
        $name = $config['name'];

        $this->fields[$handle] = Craft::$app->fields->getFieldByHandle($handle);
        if ($this->fields[$handle]) {
            return true;
        }

        /* @phpstan-ignore-next-line */
        $field = Craft::createObject($config);

        if (!Craft::$app->fields->saveField($field)) {
            $this->error("Could not save field {$handle}");
            return false;
        }

        $this->fields[$handle] = $field;

        $this->info("Field {$name} created.");
        return true;
    }

    /**
     * @param array $config
     * @return bool
     * @throws InvalidConfigException
     * @throws Throwable
     */
    protected function createMatrixField(array $config): bool
    {
        $this->fields[$config['handle']] = Craft::$app->fields->getFieldByHandle($config['handle']);
        if ($this->fields[$config['handle']]) {
            return true;
        }

        $blocktypes = [];
        $layoutConfigs = [];
        foreach ($config['blocktypes'] as $blocktypeConfig) {
            $fields = [];
            foreach ($blocktypeConfig['fields'] as $field) {
                $fieldConfig = $field;
                if (isset($fieldConfig['layoutConfig'])) {
                    $layoutConfigs[$fieldConfig['handle']] = $fieldConfig['layoutConfig'];
                    unset($fieldConfig['layoutConfig']);
                }

                /* @phpstan-ignore-next-line */
                $fields[] = Craft::createObject($fieldConfig);
            }
            $blocktype = new MatrixBlockType();
            $blocktype->handle = $blocktypeConfig['handle'];
            $blocktype->name = $blocktypeConfig['name'];
            $layout = $blocktype->getFieldLayout();
            $tab = new FieldLayoutTab();
            $tab->name = 'Content';
            $tab->sortOrder = 1;
            $tab->layout = $layout;
            $tab->setElements(collect($fields)
                ->map(function($field) use ($layoutConfigs) {
                    $layoutConfig = $layoutConfigs[$field->handle] ?? [];
                    return new CustomField($field, $layoutConfig);
                })
                ->toArray()
            );
            $layout->setTabs([$tab]);

            $blocktypes[] = $blocktype;
        }

        $matrixField = new Matrix();
        $matrixField->handle = $config['handle'];
        $matrixField->name = $config['name'];
        $matrixField->groupId = $config['groupId'];
        $matrixField->setBlockTypes($blocktypes);

        if (!Craft::$app->fields->saveField($matrixField)) {
            $this->error("Could not save field {$config['handle']}");
            return false;
        }

        $this->fields[$config['handle']] = $matrixField;

        $this->info("Field {$config['name']} created.");
        return true;
    }


    /**
     * @param string $sectionHandle
     * @param array $tabConfigs
     * @return bool
     * @throws Exception
     */
    protected function updateFieldLayout(string $sectionHandle, array $tabConfigs): bool
    {
        // Only create field layout for newly created sections
        if (!in_array($sectionHandle, $this->doUpdateFieldlayout)) {
            return true;
        }

        // Single tab
        if (isset($tabConfigs[0])) {
            $tabConfigs = [
                'Content' => $tabConfigs,
            ];
        };


        /** @var Section $section */
        $section = $this->sections[$sectionHandle];
        $layout = $section->entryTypes[0]->getFieldLayout();

        $tabs = [];

//        foreach ($tab->getElements() as $element) {
//            if ($element instanceof CustomField) {
//                /** @var CustomField $element */
//                if ($element->getField()->handle === $layoutElements[0][0]) {
//                    $this->info("Layout for $sectionHandle exists");
//                    return true;
//                }
//            }
//        }

        $sortOrder = 1;
        /** @var array $layoutElements */
        foreach ($tabConfigs as $label => $layoutElements) {
            $tab = new FieldLayoutTab();
            $tab->name = $label;
            $tab->sortOrder = $sortOrder++;
            $tab->layout = $layout;


            $tab->setElements(collect($layoutElements)
                ->map(function($layoutElement) {
                    if (is_string($layoutElement)) {
                        $layoutElement = [$layoutElement, []];
                    }

                    if ($layoutElement[0] === 'titleField') {
                        return new TitleField();
                    }

                    if ($layoutElement[0] === 'lineBreak') {
                        return new LineBreak();
                    }

                    if ($layoutElement[0] === 'tip') {
                        return new Tip($layoutElement[1]);
                    }

                    return new CustomField(Craft::$app->fields->getFieldByHandle($layoutElement[0]), $layoutElement[1]);
                })
                ->toArray()
            );


            $tabs[] = $tab;
        }

        $layout->setTabs($tabs);

        if (!Craft::$app->fields->saveLayout($layout)) {
            $this->error("Could not save fieldlayout for $sectionHandle");
            return false;
        }

        $this->info("Field layout for $sectionHandle created.");

        return true;
    }

    /**
     * @param string $heading
     * @param string $sectionHandle
     * @param array $tableAttributes
     * @return void
     * @throws Exception
     * @throws InvalidConfigException
     * @throws BusyResourceException
     * @throws StaleResourceException
     * @throws ErrorException
     * @throws NotSupportedException
     * @throws ServerErrorHttpException
     */
    protected function updateElementSource(string $heading, string $sectionHandle, array $tableAttributes): void
    {
        $config = Craft::$app->projectConfig->get('elementSources');
        $section = Craft::$app->entries->getSectionByHandle($sectionHandle);
        if (!$section) {
            $this->error("Section $sectionHandle not found.");
            return;
        }
        $key = "section:{$section->uid}";

        // Check for existing source
        foreach ($config['craft\\elements\\Entry'] as $source) {
            if ($source['type'] === 'native' && $source['key'] === $key) {
                return;
            }
        }

        // Ensure a heading is set
        $headingExists = false;
        foreach ($config['craft\\elements\\Entry'] as $source) {
            if ($source['type'] === 'heading' && $source['heading'] === $heading) {
                $headingExists = true;
                break;
            }
        }

        // Append heading
        if (!$headingExists) {
            $config['craft\\elements\\Entry'][] = [
                'heading' => $heading,
                'type' => 'heading',
            ];
        }

        // A source for newly created sections does not exist, so we can just append it.
        $config['craft\\elements\\Entry'][] = [
            'disabled' => false,
            'key' => $key,
            'tableAttributes' => $tableAttributes,
            'type' => 'native',
        ];

        Craft::$app->projectConfig->set('elementSources', $config);
        $this->info("ElementSource for $sectionHandle updated.");
    }


    /**
     * @param string $fieldGroup
     * @return FieldGroupRecord|null
     */
    protected function getFieldGroup(string $fieldGroup): ?FieldGroupRecord
    {
        return FieldGroupRecord::findOne(['name' => $fieldGroup]);
    }

    /**
     * @param int $width
     * @return Asset|null
     */
    protected function getRandomImage(int $width): ?Asset
    {
        return Asset::find()
            ->volume($this->imageVolume)
            ->kind('image')
            ->width('> ' . $width)
            ->orderBy(Craft::$app->db->driverName === 'mysql' ? 'RAND()' : 'RANDOM()')
            ->one();
    }
}
