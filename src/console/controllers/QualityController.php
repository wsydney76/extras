<?php

namespace wsydney76\extras\console\controllers;

use Craft;
use craft\console\Controller;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\fs\Local;
use craft\helpers\Console;
use GuzzleHttp\Exception\GuzzleException;
use yii\base\InvalidConfigException;
use yii\console\ExitCode;
use function count;
use const DIRECTORY_SEPARATOR;

class QualityController extends Controller
{
    // Define flags
    /**
     * @var string|array Comma separated list of section handles
     */
    public string|array $section = '';

    /**
     * @var int Number of elements to check
     */
    public int $limit = 99999;

    /**
     * @var int Offset of elements to check. Use in combination with --limit to check a subset of elements
     */
    public int $offset = 0;

    /**
     * @var string Volume handle
     */
    public string $volume = '';

    // Define allowed flags per action
    public function options($actionID): array
    {
        return match ($actionID) {
            'check-runtime-errors' => ['help', 'section', 'limit', 'offset'],
            'check-asset-files' => ['help', 'volume', 'limit', 'offset'],
            default => ['help'],
        };
    }

    public function optionAliases(): array
    {
        return [
            's' => 'section',
            'l' => 'limit',
            'o' => 'offset',
            'v' => 'volume'
        ];
    }

    /**
     * Checks all live entries for runtime errors
     *
     * @return int
     */
    public function actionCheckRuntimeErrors(): int
    {
        Console::output("Checking all live entries for runtime errors...");

        $query = Entry::find()
            ->uri(':notempty:')
            ->site('*')
            ->limit($this->limit)
            ->offset($this->offset);

        if ($this->section) {
            $this->section = explode(',', $this->section);
            // check valid section
            foreach ($this->section as $section) {
                if (!Craft::$app->entries->getSectionByHandle($section)) {
                    Console::error("Section $section not found.");
                    return ExitCode::UNSPECIFIED_ERROR;
                }
            }

            $query->section($this->section);
        }

        $entries = $query->all();

        $client = Craft::createGuzzleClient();

        $errors = 0;
        $ok = 0;
        $has403 = false;
        $messages = [];

        $totalEntries = count($entries);
        Console::startProgress(0, $totalEntries, 'Checking...');

        foreach ($entries as $index => $entry) {
            Console::updateProgress($index + 1, $totalEntries, "Checking, found $errors error(s) so far.");

            $url = $entry->url;

            try {
                $response = $client->get($url);
            } catch (GuzzleException $e) {
                $statusCode = $e->getCode();
                if ($statusCode === 403) {
                    $has403 = true;
                } else {
                    $messages[] = "Error: $url returned $statusCode";
                    $errors++;
                }
                continue;
            }

            // All errors should be caught by the try/catch block, but who knows...
            $statusCode = $response->getStatusCode();
            if ($statusCode !== 200) {
                $errors++;
                $messages[] = "Error: $url returned $statusCode";
                continue;
            }

            $ok++;
        }

        Console::endProgress();

        foreach ($messages as $message) {
            Console::output($message);
        }

        if ($has403) {
            Console::output("Some URLs are protected and could not be checked.");
        }

        Console::output("$ok URLs without runtime error.");

        if ($errors > 0) {
            Console::output("$errors runtime errors found.");
        } else {
            Console::output("No runtime errors found. Don't rejoice too soon.");
        }

        return ExitCode::OK;
    }

    /**
     * Checks all asset files for existence
     *
     * @return int
     * @throws InvalidConfigException
     */
    public function actionCheckAssetFiles()
    {
        $query = Asset::find()
            ->limit($this->limit)
            ->offset($this->offset);

        if ($this->volume) {
            // check valid volume
            if (!Craft::$app->volumes->getVolumeByHandle($this->volume)) {
                Console::error("Volume {$this->volume} not found.");
                return ExitCode::UNSPECIFIED_ERROR;
            }

            $query->volume($this->volume);
        }

        $assets = $query->all();

        $totalAssets = count($assets);
        Console::startProgress(0, $totalAssets, 'Checking...');

        $errors = 0;
        $ok = 0;
        $messages = [];

        /** @var Asset $asset */
        /** @var Local $fs */
        foreach ($assets as $index => $asset) {

            Console::updateProgress($index + 1, $totalAssets, "Checking, found $errors error(s) so far.");
            $fs = $asset->volume->getFs();

            $path = $fs->getRootPath() . DIRECTORY_SEPARATOR . $asset->volume->getSubpath();

            if ($asset->folderPath !== null) {
                $path .= DIRECTORY_SEPARATOR . $asset->folderPath;
            }

            $path .= $asset->filename;

            if (!file_exists($path)) {
                $errors++;
                $messages[] = "File not found: $asset->id $path";
                continue;
            }

            $ok++;
        }

        Console::endProgress();

        foreach ($messages as $message) {
            Console::output($message);
        }

        Console::output("$ok files found.");

        if ($errors > 0) {
            Console::output("$errors missing files found.");
        } else {
            Console::output("No missing files found.");
        }

        return ExitCode::OK;
    }

    public function actionConsolidateFieldsCandidates()
    {
        $signatures = [];

        foreach (Craft::$app->getFields()->getAllFields() as $field) {
            $signature = [
                'type' => get_class($field),
                'translationMethod' => $field->translationMethod,
                'translationKeyFormat' => $field->translationKeyFormat,
                'searchable' => $field->searchable,
                'settings' => $field->settings,
            ];

            $hash = md5(json_encode($signature));

            $signatures[$hash][] = $field->handle;
        }
        foreach ($signatures as $hash => $handles) {
            if (count($handles) > 1) {
                Console::output(implode(', ', $handles));
            }
        }
    }
}
