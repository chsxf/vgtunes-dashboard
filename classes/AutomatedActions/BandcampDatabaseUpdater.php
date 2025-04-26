<?php

namespace AutomatedActions;

use chsxf\MFX\DataValidator;
use chsxf\MFX\HttpStatusCodes;
use Exception;
use Platform;
use PlatformHelpers\PlatformHelperException;
use PlatformHelpers\PlatformHelperFactory;

class BandcampDatabaseUpdater extends AbstractSequentialAutomatedAction
{
    public function setUp(DataValidator $validator): void
    {
        $dbService = $this->coreServiceProvider->getDatabaseService();
        $dbConn = $dbService->open();

        $limit = $validator->getFieldValue(self::LIMIT_OPTION, true);
        $firstId = $validator->getFieldValue(self::FIRST_ID_OPTION, true);

        $values = [];

        $sql = "SELECT DISTINCT `album_id`
                    FROM `album_instances`
                    WHERE `album_id` NOT IN (SELECT DISTINCT `album_id` FROM `album_instances` WHERE `platform` = 'bandcamp')";
        if ($firstId > 0) {
            $sql .= " AND `album_id` >= ?";
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
        if (!is_array($albumIds) || !is_int($currentIndex)) {
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
            $stepData->addLogLine("Looking for matches for album Id '{$currentAlbumId}'...");

            $platformHelper = PlatformHelperFactory::get(Platform::bandcamp, $this->coreServiceProvider);

            $sql = "SELECT `title` FROM `albums` WHERE `id` = ?";
            if (($albumTitle = $dbConn->getValue($sql, $currentAlbumId)) === false) {
                throw new Exception('  Unable to fetch album details');
            }

            $sql = "SELECT `ar`.`name`
                        FROM `album_artists` AS `aa`
                        LEFT JOIN `artists` AS `ar`
                            ON `aa`.`artist_id` = `ar`.`id`
                        WHERE `aa`.`album_id` = ?
                        ORDER BY `ar`.`name`";
            if (($artists = $dbConn->getColumn($sql, $currentAlbumId)) === false) {
                throw new Exception('  Unable to fetch album artists');
            }
            $displayableArtists = implode(', ', $artists);

            $stepData->addLogLine("  Album: {$albumTitle} - {$displayableArtists}");

            $exactMatch = $platformHelper->searchExactMatch($albumTitle, $artists);
            if ($exactMatch === null) {
                $stepData->addLogLine('  No match for this album', AutomatedActionLogType::warning);
            } else {
                $matchArtists = implode(', ', $exactMatch['artists']);
                $stepData->addLogLine("  Match found: {$exactMatch['title']} - {$matchArtists}");

                $sql = "INSERT INTO `album_instances` VALUE (?, ?, ?)";
                if ($dbConn->exec($sql, $currentAlbumId, Platform::bandcamp->value, $exactMatch['platform_id']) === false) {
                    throw new Exception('  Unable to save match');
                }
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
