<?php

/* **************** TODO: UPGRADE TO CRAFT 5 **************** */

namespace wsydney76\extras\base\services;

use Craft;
use craft\elements\Entry;
use craft\elements\User;
use craft\errors\ElementNotFoundException;
use craft\errors\InvalidFieldException;
use craft\errors\SiteNotFoundException;
use craft\fields\Matrix;
use craft\helpers\ArrayHelper;
use Throwable;
use yii\base\Exception;

/**
 * Content Service
 */
class BaseContentService extends BaseService
{
    public function test(): string
    {
        return 'Hey, it works!';
    }

    /**
     * @param array $data
     * @param bool $overwrite
     * @return mixed
     * @throws Throwable
     * @throws ElementNotFoundException
     * @throws InvalidFieldException
     * @throws SiteNotFoundException
     * @throws Exception
     */
    public function createEntry(array $data, bool $overwrite = false): mixed
    {
        $user = User::find()->admin()->one();

        ['section' => $sectionHandle, 'type' => $typeHandle, 'title' => $title] = $data;

        if (isset($data['site'])) {
            $siteId = Craft::$app->sites->getSiteByHandle($data['site'])->id;
        } else {
            $siteId = Craft::$app->sites->getPrimarySite()->id;
        }

        if (isset($data['slug']) && $overwrite === false) {
            $entry = Entry::find()->section($sectionHandle)->siteId($siteId)->slug($data['slug'])->one();

            if ($entry) {
                $this->error("Entry $title exists");
                return $entry;
            }
        }

        $section = Craft::$app->entries->getSectionByHandle($sectionHandle);

        if (!$section) {
            $this->error("Section $sectionHandle does not exist.");
            return null;
        }

        $type = ArrayHelper::firstWhere($section->getEntryTypes(), 'handle', $typeHandle);
        if (!$type) {
            $this->error("Type $typeHandle does not exist.");
            return null;
        }

        $entry = new Entry([
            'sectionId' => $section->id,
            'typeId' => $type->id,
            'siteId' => $siteId,
            'title' => $title,
        ]);

        if (isset($data['slug'])) {
            $entry->slug = $data['slug'];
        }

        if (isset($data['author'])) {
            $entry->authorId = $data['author']->id;
        } else {
            $entry->authorId = $user->id;
        }

        if (isset($data['postDate'])) {
            $entry->postDate = $data['postDate'];
        }

        if (isset($data['parent'])) {
            $entry->setParentId($data['parent']->id);
        }

        if (isset($data['fields'])) {
            foreach ($data['fields'] as $handle => $value) {
                $field = Craft::$app->fields->getFieldByHandle($handle);

                if ($field instanceof Matrix && !isset($value['sortOrder'])) {
                    $blocks = $value;
                    $value = ['sortOrder' => [], 'blocks' => []];
                    foreach ($blocks as $i => $block) {
                        $value['sortOrder'][] = $i;
                        $value['blocks'][$i] = $block;
                    }
                }

                $entry->setFieldValue($handle, $value);
            }
        }

        if (!Craft::$app->elements->saveElement($entry)) {
            $this->error("Error saving entry $title: " . implode(', ', $entry->getErrorSummary(true)));
            return null;
        }

        $this->info("Entry $entry->title saved.");

        if (isset($data['localized'])) {
            foreach ($data['localized'] as $siteHandle => $localizedContent) {
                $localizedEntry = Entry::find()->id($entry->id)->site($siteHandle)->one();
                if ($localizedEntry) {
                    if (isset($localizedContent['title'])) {
                        $localizedEntry->title = $localizedContent['title'];
                    }
                    if (isset($localizedContent['slug'])) {
                        $localizedEntry->slug = $localizedContent['slug'];
                    }

                    if (isset($localizedContent['fields'])) {
                        foreach ($localizedContent['fields'] as $handle => $value) {
                            $field = Craft::$app->fields->getFieldByHandle($handle);
                            if ($field instanceof Matrix && !isset($value['sortOrder'])) {
                                $blocks = $value;

                                $primaryBlocks = $entry->getFieldValue($handle)->all();

                                $value = ['sortOrder' => [], 'blocks' => []];
                                foreach ($blocks as $i => $block) {
                                    $id = $primaryBlocks[$i]->id;
                                    $value['sortOrder'][] = $id;
                                    $value['blocks'][$id] = $block;
                                }
                            }

                            $localizedEntry->setFieldValue($handle, $value);
                        }
                    }

                    if (!Craft::$app->elements->saveElement($localizedEntry)) {
                        $this->error("Error saving localized entry $siteHandle $title: " . implode(', ', $localizedEntry->getErrorSummary(true)));
                    }
                }
            }
        }

        return $entry;
    }
}
