<?php

namespace AutomatedActions;

use Artist;
use AutomatedActions\AbstractSequentialAutomatedAction;
use AutomatedActions\AutomatedActionStatus;
use chsxf\MFX\DataValidator;
use AutomatedActions\AutomatedActionStepData;
use chsxf\MFX\DataValidator\Field;
use chsxf\MFX\DataValidator\Fields\WithOptions;
use chsxf\MFX\DataValidator\FieldType;
use chsxf\MFX\HttpStatusCodes;
use Exception;
use Platform;
use PlatformHelpers\PlatformHelperException;
use PlatformHelpers\PlatformHelperFactory;

final class MultiArtistsUpdater extends AbstractSequentialAutomatedAction
{
    private const string PLATFORM_OPTION = 'platform';

    public function getOptions(): array
    {
        $options = parent::getOptions();

        $platformOption = Field::create(self::PLATFORM_OPTION, FieldType::SELECT, defaultValue: Platform::deezer->value, required: true);
        $platformOption->addExtra('class', 'form-control');
        if ($platformOption instanceof WithOptions) {
            $supportedPlatforms = [
                Platform::appleMusic->value => Platform::appleMusic->getLabel(),
                Platform::deezer->value => Platform::deezer->getLabel(),
                Platform::spotify->value => Platform::spotify->getLabel()
            ];
            $platformOption->addOptions($supportedPlatforms);
        }
        $options[] = $platformOption;

        return $options;
    }

    public function setUp(DataValidator $validator): void
    {
        $dbService = $this->coreServiceProvider->getDatabaseService();
        $dbConn = $dbService->open();

        $limit = $validator->getFieldValue(self::LIMIT_OPTION, true);
        $firstId = $validator->getFieldValue(self::FIRST_ID_OPTION, true);
        $platform = $validator->getFieldValue(self::PLATFORM_OPTION, true);

        $values = [$platform];

        $sql = "SELECT DISTINCT `id`
                    FROM `albums`
                    WHERE FIND_IN_SET('multi_artists', `feature_flags`) = 0
                        AND `id` IN (SELECT DISTINCT `album_id` FROM `album_instances` WHERE `platform` = ?)";
        if ($firstId > 0) {
            $sql .= " AND `id` >= ?";
            $values[] = $firstId;
        }
        if ($limit > 0) {
            $sql .= " LIMIT {$limit}";
        }
        if (($ids = $dbConn->getColumn($sql, $values)) === false) {
            throw new Exception('Unable to fetch album Ids with missing Bandcamp link');
        }

        $dbService->close($dbConn);

        $sessionData = [
            self::PLATFORM_OPTION => $platform,
            self::CURRENT_INDEX => 0,
            self::ALBUM_IDS => $ids
        ];
        $this->storeInSession(self::PROGRESS_DATA, $sessionData);
    }

    public function proceedWithNextStep(): AutomatedActionStepData
    {
        $sessionData = $this->getFromSession(self::PROGRESS_DATA);
        if ($sessionData === null) {
            return new AutomatedActionStepData(AutomatedActionStatus::failed, HttpStatusCodes::internalServerError, 'Unable to retrieve session data', AutomatedActionLogType::error);
        }

        $albumIds = $sessionData[self::ALBUM_IDS] ?? null;
        $currentIndex = $sessionData[self::CURRENT_INDEX] ?? null;
        $platform = Platform::tryFrom($sessionData[self::PLATFORM_OPTION]);
        if (!is_array($albumIds) || !is_int($currentIndex) || $platform === null) {
            return new AutomatedActionStepData(AutomatedActionStatus::failed, HttpStatusCodes::internalServerError, 'Invalid session data structure', AutomatedActionLogType::error);
        }

        $stepData = new AutomatedActionStepData();
        $stepData->totalItems = count($albumIds);
        $stepData->currentItemNumber = $currentIndex + 1;

        if ($currentIndex >= count($albumIds)) {
            $stepData->status = AutomatedActionStatus::complete;
            return $stepData;
        }

        $currentAlbumId = $albumIds[$currentIndex];

        $dbService = $this->coreServiceProvider->getDatabaseService();
        $dbConn = $dbService->open();

        $dbConn->beginTransaction();

        try {
            $platformHelper = PlatformHelperFactory::get($platform, $this->coreServiceProvider);

            $stepData->addLogLine("Looking for artists for album Id '{$currentAlbumId}'...");

            $sql = "SELECT `title` FROM `albums` WHERE `id` = ?";
            if (($title = $dbConn->getValue($sql, $currentAlbumId)) === false) {
                throw new Exception("  Unable to fetch album's title");
            }
            $stepData->addLogLine("  Title: {$title}");

            $sql = "SELECT `platform_id` FROM `album_instances` WHERE `platform` = ? AND `album_id` = ?";
            if (($platformId = $dbConn->getValue($sql, $platform->value, $currentAlbumId)) === false) {
                throw new Exception("  Unable to fetch album's platform ID");
            }

            $sql = "SELECT `artist_id` FROM `album_artists` WHERE `album_id` = ? AND `artist_order` != 0";
            if (($existingArtists = $dbConn->getColumn($sql, $currentAlbumId)) === false) {
                throw new Exception("  Unable to fetch album's existing artists");
            }

            $albumDetails = $platformHelper->getAlbumDetails($platformId);
            if ($albumDetails === null) {
                throw new Exception("  Unable to retrieve album details from the selected platform");
            }

            $newArtists = [];
            foreach ($albumDetails->artists as $newArtist) {
                $newArtists[] = Artist::getOrCreateArtistId($dbConn, $newArtist);
            }

            $artistIntersection = array_intersect($existingArtists, $newArtists);
            $hasNewArtist = count($artistIntersection) != count($newArtists);
            if (!$hasNewArtist) {
                $stepData->addLogLine("  No artist added");
            } else {
                $sql = "DELETE FROM `album_artists` WHERE `album_id` = ?";
                if ($dbConn->exec($sql, $currentAlbumId) === false) {
                    throw new Exception("  Unable to remove previous artist entries");
                } else {
                    $currentOrder = 1;
                    foreach ($newArtists as $newArtistId) {
                        $sql = "INSERT INTO `album_artists` VALUE (?, ?, ?)";
                        if ($dbConn->exec($sql, $currentAlbumId, $newArtistId, $currentOrder) === false) {
                            throw new Exception("  Unable to add artist to the current album");
                        }
                        $currentOrder++;
                    }

                    $stepData->addLogLine(sprintf("  Updated album with %d artists", count($newArtists)), AutomatedActionLogType::warning);
                }
            }

            $sql = "UPDATE `albums`
                        SET `feature_flags` = TRIM(BOTH ',' FROM CONCAT(`feature_flags`, ',', 'multi_artists'))
                        WHERE `id` = ?";
            if ($dbConn->exec($sql, $currentAlbumId) === false) {
                throw new Exception("  Unable to set feature flag for current album");
            }

            $dbConn->commit();

            $sessionData[self::CURRENT_INDEX] = $currentIndex + 1;
            $this->storeInSession(self::PROGRESS_DATA, $sessionData);

            return $stepData;
        } catch (Exception $e) {
            $dbConn->rollBack();
            $stepData->status = AutomatedActionStatus::failed;
            if ($e instanceof PlatformHelperException && $e->statusCode !== null) {
                $stepData->httpStatusCode = $e->statusCode;
            }
            $stepData->addLogLine($e->getMessage(), AutomatedActionLogType::error);
        }

        return $stepData;
    }

    public function shutDown(): void
    {
        $this->removeFromSession(self::PROGRESS_DATA);
    }
}
