<?php

namespace AutomatedActions;

use chsxf\MFX\DataValidator;
use chsxf\MFX\HttpStatusCodes;
use Exception;
use Platform;
use PlatformHelpers\PlatformHelperException;
use PlatformHelpers\PlatformHelperFactory;

class SteamDatabaseUpdater extends AbstractSequentialAutomatedAction
{
    private const string PROGRESS_DATA = 'progress';
    private const string CURRENT_INDEX = 'current_index';
    private const string GAME_APP_IDS = 'game_app_ids';
    private const string SOUNDTRACK_APP_IDS = 'soundtrack_app_ids';

    public function setUp(DataValidator $validator): void
    {
        $dbService = $this->coreServiceProvider->getDatabaseService();
        $dbConn = $dbService->open();

        $limit = $validator->getFieldValue(self::LIMIT_OPTION, true);
        $firstId = $validator->getFieldValue(self::FIRST_ID_OPTION, true);

        $values = [];

        $sqlTemplate = "SELECT DISTINCT `album_id`
                    FROM `album_instances`
                    WHERE `album_id` NOT IN (SELECT DISTINCT `album_id` FROM `album_instances` WHERE `platform` = '%s')";
        if ($firstId > 0) {
            $sqlTemplate .= " AND `album_id` >= ?";
            $values[] = $firstId;
        }
        if ($limit > 0) {
            $sqlTemplate .= " LIMIT {$limit}";
        }

        $sql = sprintf($sqlTemplate, Platform::steamGame->value);
        if (($gameIds = $dbConn->getColumn($sql, $values)) === false) {
            throw new Exception('Unable to fetch album Ids with missing Steam game link');
        }

        $sql = sprintf($sqlTemplate, Platform::steamSoundtrack->value);
        if (($soundtrackIds = $dbConn->getColumn($sql, $values)) === false) {
            throw new Exception('Unable to fetch album Ids with missing Steam soundtrack link');
        }

        $soundtrackIds = array_values(array_diff($soundtrackIds, $gameIds));

        $dbService->close($dbConn);

        $sessionData = [
            self::CURRENT_INDEX => 0,
            self::GAME_APP_IDS => $gameIds,
            self::SOUNDTRACK_APP_IDS => $soundtrackIds
        ];
        $this->storeInSession(self::PROGRESS_DATA, $sessionData);
    }

    public function proceedWithNextStep(): AutomatedActionStepData
    {
        $sessionData = $this->getFromSession(self::PROGRESS_DATA);
        if ($sessionData === null) {
            return new AutomatedActionStepData(AutomatedActionStatus::failed, HttpStatusCodes::internalServerError, 'Unable to retrieve session data', AutomatedActionLogType::error);
        }

        $currentIndex = $sessionData[self::CURRENT_INDEX] ?? null;
        $gameIds = $sessionData[self::GAME_APP_IDS] ?? null;
        $soundtrackIds = $sessionData[self::SOUNDTRACK_APP_IDS] ?? null;
        if (!is_array($gameIds) || !is_array($soundtrackIds) || !is_int($currentIndex)) {
            return new AutomatedActionStepData(AutomatedActionStatus::failed, HttpStatusCodes::internalServerError, 'Invalid session data structure', AutomatedActionLogType::error);
        }

        $stepData = new AutomatedActionStepData();
        $stepData->totalItems = count($gameIds) + count($soundtrackIds);
        $stepData->currentItemNumber = $currentIndex + 1;

        if ($currentIndex >= $stepData->totalItems) {
            $stepData->status = AutomatedActionStatus::complete;
            return $stepData;
        }

        if ($currentIndex < count($gameIds)) {
            $currentAlbumId = $gameIds[$currentIndex];
            $isGame = true;
        } else {
            $currentAlbumId = $soundtrackIds[$currentIndex - count($gameIds)];
            $isGame = false;
        }

        $dbService = $this->coreServiceProvider->getDatabaseService();
        $dbConn = $dbService->open();

        $dbConn->beginTransaction();

        try {
            $queryCategory = $isGame ? "game" : "soundtrack";
            $stepData->addLogLine("Looking for {$queryCategory} matches for album Id '{$currentAlbumId}'...");

            $platform = $isGame ? Platform::steamGame : Platform::steamSoundtrack;
            $platformHelper = PlatformHelperFactory::get($platform, $this->coreServiceProvider);

            $sql = "SELECT `title` FROM `albums` WHERE `id` = ?";
            if (($album = $dbConn->getRow($sql, \PDO::FETCH_ASSOC, $currentAlbumId)) === false) {
                throw new Exception('  Unable to fetch album details');
            }
            $stepData->addLogLine("  Album: {$album['title']}");

            $exactMatch = $platformHelper->searchExactMatch($album['title'], '<ignored>');
            if ($exactMatch === null) {
                $stepData->addLogLine('  No match for this album', AutomatedActionLogType::warning);
            } else {
                $stepData->addLogLine("  Match found: {$exactMatch['title']}");

                $sql = "INSERT INTO `album_instances` VALUE (?, ?, ?)";
                if ($dbConn->exec($sql, $currentAlbumId, $platform->value, $exactMatch['platform_id']) === false) {
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
