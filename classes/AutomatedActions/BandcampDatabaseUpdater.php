<?php

namespace AutomatedActions;

use Exception;
use Platform;
use PlatformHelpers\PlatformHelperFactory;

class BandcampDatabaseUpdater extends AbstractAutomatedAction
{
    private const string ALBUM_IDS = 'album_ids';

    public function setUp(): void
    {
        $dbService = $this->coreServiceProvider->getDatabaseService();
        $dbConn = $dbService->open();

        $sql = "SELECT DISTINCT `album_id`
                    FROM `album_instances`
                    WHERE `album_id` NOT IN (SELECT DISTINCT `album_id` FROM `album_instances` WHERE `platform` = 'bandcamp')";
        if (($ids = $dbConn->getColumn($sql)) === false) {
            throw new Exception('Unable to fetch album Ids with missing Bandcamp link');
        }

        $dbService->close($dbConn);

        $sessionData = [
            'current_index' => 0,
            'album_ids' => $ids
        ];
        $this->storeInSession(self::ALBUM_IDS, $sessionData);
    }

    public function proceedWithNextStep(): AutomatedActionStepData
    {
        $sessionData = $this->getFromSession(self::ALBUM_IDS);
        if ($sessionData === null) {
            return new AutomatedActionStepData(AutomatedActionStatus::failed, 'Unable to retrieve session data', AutomatedActionLogType::error);
        }

        $albumIds = $sessionData['album_ids'] ?? null;
        $currentIndex = $sessionData['current_index'] ?? null;
        if (!is_array($albumIds) || !is_int($currentIndex)) {
            return new AutomatedActionStepData(AutomatedActionStatus::failed, 'Invalid session data structure', AutomatedActionLogType::error);
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

            $sql = "SELECT `al`.`title`, `ar`.`name` AS `artist_name`
                        FROM `albums` AS `al`
                        LEFT JOIN `artists` AS `ar`
                            ON `ar`.`id` = `al`.`artist_id`
                        WHERE `al`.`id` = ?";
            if (($album = $dbConn->getRow($sql, \PDO::FETCH_ASSOC, $currentAlbumId)) === false) {
                throw new Exception('  Unable to fetch album details');
            }
            $stepData->addLogLine("  Album: {$album['title']} - {$album['artist_name']}");

            $exactMatch = $platformHelper->searchExactMatch($album['title'], $album['artist_name']);
            if ($exactMatch === null) {
                $stepData->addLogLine('  No match for this album', AutomatedActionLogType::warning);
            } else {
                $stepData->addLogLine("  Match found: {$album['title']} - {$album['artist_name']}");

                $sql = "INSERT INTO `album_instances` VALUE (?, 'bandcamp', ?)";
                if ($dbConn->exec($sql, $currentAlbumId, $exactMatch['platform_id']) === false) {
                    throw new Exception('  Unable to save match');
                }
            }

            $dbConn->commit();

            $sessionData['current_index'] = $currentIndex + 1;
            $this->storeInSession(self::ALBUM_IDS, $sessionData);

            return $stepData;
        } catch (Exception $e) {
            $dbConn->rollBack();
            $stepData->status = AutomatedActionStatus::failed;
            $stepData->addLogLine($e->getMessage(), AutomatedActionLogType::error);
        }

        return $stepData;
    }

    public function shutDown(): void
    {
        $this->removeFromSession(self::ALBUM_IDS);
    }
}
