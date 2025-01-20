<?php

namespace wsydney76\extras\services;

use Craft;
use craft\base\Component;
use craft\elements\Asset;
use craft\fs\Local;
use craft\helpers\Console;
use Exception;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\FFMpeg;
use Throwable;


/**
 * Video Service
 */
class VideoService extends Component
{
    /**
     * Creates a video poster for the given video asset.
     *
     * @param Asset $video The video asset for which to create a poster.
     * @param int $fromSeconds The time in seconds from which to take the frame for the poster.
     * @param bool $replace Whether to replace an existing poster.
     * @param string $posterField The field name to store the poster asset.
     * @return void
     * @throws \yii\base\Exception
     * @throws Throwable
     */
    public function createVideoPoster(Asset $video, int $fromSeconds = 1, bool $replace = false, string $posterField = 'videoPoster'): bool
    {
        if ($video->kind !== 'video') {
            $this->error('Asset is not a video.');
            return false;
        }

        $existingPoster = $video->$posterField->one();

        $elements = Craft::$app->getElements();

        if ($existingPoster) {
            if (!$replace) {
                return true;
            }
            // Replace: Keep it simple, just delete the existing poster and start from scratch
            // TODO: Delay until new poster is successfully created
            $elements->deleteElement($existingPoster);
        }

        $volume = $video->getVolume();

        /** @var Local $fileSystem */
        $fileSystem = $volume->fs;
        if (!$fileSystem instanceof Local) {
            $this->error('Only volumes with local file system are supported.');
            return false;
        }

        $videoPath = $fileSystem->getRootPath() . DIRECTORY_SEPARATOR .
            $volume->getSubpath() . DIRECTORY_SEPARATOR .
            $video->folderPath . // folderPath has trailing slash
            $video->filename;

        if (!file_exists($videoPath)) {
            $this->error('Video file does not exist: ' . $videoPath);
            return false;
        }

        // Use the same path/filename for the image as the video, but with a .jpg extension
        $pathInfo = pathinfo($videoPath);
        $imageFilename = $pathInfo['filename'] . '.jpg';
        $imagePath = $pathInfo['dirname'] . DIRECTORY_SEPARATOR . $imageFilename;

        $assetIndexer = Craft::$app->getAssetIndexer();
        
        $indexingSession = $assetIndexer->createIndexingSession([$volume]);

        // Flag to indicate if an error occurred,
        // don't just jump out with 'return false' on errors, because we have to stop the indexing session at the end
        $hasError = false;

        try {
            $ffmpeg = FFMpeg::create();

            $ffmpeg->open($videoPath)
                ->frame(TimeCode::fromSeconds($fromSeconds))
                ->save($imagePath);


            // Create an asset element for the image
            $image = $assetIndexer->indexFile(
                $volume,
                $video->folderPath . $imageFilename,
                $indexingSession->id);

            // indexFile() signature doesn't allow a null return value,
            // so assuming that all errors will be caught by try/catch

            // Better focal point for portrait videos
            if ($image->height > $image->width) {
                $image->focalPoint = [
                    'x' => 0.5,
                    'y' => 0.25
                ];
                if (!$elements->saveElement($image)) {
                    $hasError = true;
                    $this->error("Error saving image for video $video->id " . implode(', ', $image->firstErrors));
                }
            }

            $video->$posterField = [$image->id];
            if (!$elements->saveElement($video)) {
                $hasError = true;
                $this->error("Error saving video $video->id " . implode(', ', $video->firstErrors));
            }
        } catch (Exception $e) {
            $hasError = true;
            $this->error("Error indexing asset for video $video->id " . $e->getMessage());
        }

        $assetIndexer->stopIndexingSession($indexingSession);

        return !$hasError;
    }

    private function error(string $message): void
    {
        if (Craft::$app->getRequest()->getIsConsoleRequest()) {
            Console::error($message);
        }
        Craft::error($message);
    }
}
