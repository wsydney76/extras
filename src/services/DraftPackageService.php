<?php

namespace wsydney76\extras\services;

use Craft;
use craft\base\Element;
use craft\elements\Entry;
use wsydney76\extras\ExtrasPlugin;
use yii\base\Component;

/**
 * Draft Package Service service
 */
class DraftPackageService extends Component
{
    /**
     * @param mixed $package
     * @return array|\craft\base\ElementInterface[]
     */
    public function getElementsForPackage(mixed $package): array
    {
        $query = Entry::find()
            ->drafts()
            ->site('*')
            ->unique()
            ->status(null)
            ->relatedTo([
                'targetElement' => $package,
                'field' => 'draftPackage',
            ]);

        if (ExtrasPlugin::getInstance()->getSettings()->includeProvisionalDraftsInPackage) {
            $query->provisionalDrafts(null);
        }

        $entries = $query->all();

        foreach ($entries as $entry) {
            $entry->scenario = Element::SCENARIO_LIVE;
            $entry->validate();
            foreach ($entries as $otherEntry) {
                if ($entry !== $otherEntry && $entry->canonicalId === $otherEntry->canonicalId) {
                    $entry->addError('canonicalId',
                        Craft::t(
                            '_extras',
                            'Duplicate canonical ID {id} found.',
                            ['id' => $entry->canonicalId]));
                    break;
                }
            }
        }
        return $entries;
    }

    /**
     * @param mixed $backupDb
     * @param array $entries
     * @param mixed $afterSuccess
     * @param Entry $package
     * @return array
     * @throws Throwable
     * @throws \craft\errors\ShellCommandException
     * @throws \yii\base\Exception
     * @throws \yii\db\Exception
     */
    public function applyDrafts(Entry $package, array $entries, mixed $afterSuccess, bool $backupDb): array
    {
        $messages = [];
        $settings = ExtrasPlugin::getInstance()->getSettings();

        if ($backupDb) {
            $filePath = Craft::$app->db->backup();
            $messages[] = Craft::t('_extras', 'Database backup created at {filePath}', [
                'filePath' => $filePath,
            ]);
        }

        $hasErrors = false;
        $transaction = Craft::$app->getDb()->beginTransaction();
        foreach ($entries as $i => $entry) {

            try {

                if ($afterSuccess === 'detach') {
                    $entry->setFieldValue($settings->draftPackageField, []);
                    Craft::$app->elements->saveElement($entry);
                }
                $updatedElement = Craft::$app->drafts->applyDraft($entry, [
                    'updateSearchIndexForOwner' => true,
                ]);
                $messages[] = Craft::t('_extras', 'Draft {title} applied successfully', [
                    'title' => $updatedElement->title,
                ]);
            } catch (Throwable $e) {
                $hasErrors = true;
                $messages[] = $entry->id . ' ' . $e->getMessage();
            }
        }

        if ($hasErrors) {
            $transaction->rollBack();
            $messages[] = Craft::t('_extras', 'Draft package application failed. No changes have been applied.');
        } else {
            $transaction->commit();
            if ($afterSuccess === 'delete') {
                Craft::$app->elements->deleteElement($package);
                $messages[] = Craft::t('_extras', 'Draft package deleted');
            }
        }

        // log messages
        foreach ($messages as $message) {
            Craft::info($package->title . ': ' . $message, __METHOD__);
        }

        return $messages;
    }
}
