<?php

namespace wsydney76\extras\console\controllers;

use craft\elements\Entry;
use craft\errors\ShellCommandException;
use wsydney76\extras\ExtrasPlugin;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\db\Exception;

class DraftPackageController extends Controller
{

    public string $onsuccess = '';
    public bool $backupdb = false;

    public function options($actionID): array
    {
        if ($actionID === 'apply') {
            return array_merge(parent::options($actionID), ['onsuccess', 'backupdb']);
        }
        return parent::options($actionID);
    }

    /**
     * Apply drafts from a package via CLI.
     *
     * @param string $draftPackageSlug
     * @param string $afterSuccess
     * @param bool $backupDb
     * @throws Exception
     * @throws \yii\base\Exception
     * @throws ShellCommandException
     */
    public function actionApply(string $draftPackageSlug)
    {
        $setttings = ExtrasPlugin::getInstance()->getSettings();

        $allowedValues = ['detach', 'delete'];
        if ($this->onsuccess && !in_array($this->onsuccess, $allowedValues, true)) {
            $this->stderr("Invalid value for onsuccess. Allowed values are: " . implode(', ', $allowedValues) . "\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $package = Entry::findOne(['slug' => $draftPackageSlug, 'section' => $setttings->draftPackageSection]);
        if (!$package) {
            $this->stderr("Draft package not found\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $draftPackageService = ExtrasPlugin::getInstance()->draftPackageService;
        $entries = $draftPackageService->getElementsForPackage($package);

        if (empty($entries)) {
            $this->stdout("No drafts found for package\n");
            return ExitCode::OK;
        }

        $hasErrors = false;
        foreach ($entries as $entry) {
            if ($entry->hasErrors()) {
                $hasErrors = true;
               $this->stderr("$entry->title contains errors: " . implode(', ', $entry->getErrorSummary(true)) . "\n");
            }
        }

        if ($hasErrors) {
            $this->stderr("Draft package contains drafts that do not validate. See Utility for details\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        if ($this->interactive && !$this->confirm("Apply " . count($entries) . " draft(s) from package '$package->title'?")) {
            $this->stdout("Operation cancelled\n");
            return ExitCode::OK;
        }

        $messages = $draftPackageService->applyDrafts($package, $entries, $this->onsuccess, $this->backupdb);

        foreach ($messages as $message) {
            $this->stdout($message . "\n");
        }
        $this->stdout("Drafts applied successfully\n");
        return ExitCode::OK;
    }
}