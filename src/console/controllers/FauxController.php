<?php

namespace wsydney76\extras\console\controllers;

use Craft;
use craft\console\Controller;
use craft\helpers\App;
use craft\helpers\Console;
use yii\console\ExitCode;

class FauxController extends Controller
{
    public function actionCopy(): int
    {
        $this->stdout("Starting faux sync...\n");
        $exit = ExitCode::OK;
        try {
            $destDir = App::env('FAUX_DIRECTORY');

            if (!$destDir) {
                $destDir = Craft::$app->getPath()->getConfigPath();
                Console::output("FAUX_DIRECTORY not set, using config path: $destDir");
            }
            if (!is_dir($destDir)) {
                throw new \RuntimeException("Missing directory: $destDir");
            }

            $compiledDir =
                Craft::$app->getPath()->getRuntimePath() . DIRECTORY_SEPARATOR . 'compiled_classes';
            if (!is_dir($compiledDir)) {
                throw new \RuntimeException("Missing directory: $compiledDir");
            }

            // Delete existing CustomFieldBehavior* files in _faux
            foreach (
                glob($destDir . DIRECTORY_SEPARATOR . 'CustomFieldBehavior*') ?: []
                as $oldFile
            ) {
                if (is_file($oldFile) && @unlink($oldFile)) {
                    Console::output('Deleted: ' . basename($oldFile));
                }
            }

            $sourceFiles = glob($compiledDir . DIRECTORY_SEPARATOR . 'CustomFieldBehavior*') ?: [];
            if (!$sourceFiles) {
                Console::error("No CustomFieldBehavior* file found in $compiledDir\n");
                return ExitCode::UNSPECIFIED_ERROR;
            }

            $sourceFile = $sourceFiles[0];
            $destFile = $destDir . DIRECTORY_SEPARATOR . basename($sourceFile);
            if (!@copy($sourceFile, $destFile)) {
                throw new \RuntimeException("Copy failed: $sourceFile -> $destFile");
            }
            Console::output('Copied ' . basename($sourceFile) . ' to destination ' . $destDir);
        } catch (\Throwable $e) {
            Console::error('Error: ' . $e->getMessage() . "\n");
            $exit = ExitCode::UNSPECIFIED_ERROR;
        }
        return $exit;
    }
}