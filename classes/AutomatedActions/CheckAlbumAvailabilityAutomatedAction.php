<?php

namespace AutomatedActions;

use chsxf\MFX\DataValidator;
use chsxf\MFX\HttpStatusCodes;
use Exception;
use Platform;
use PlatformAvailability;
use PlatformHelpers\PlatformHelperException;
use PlatformHelpers\PlatformHelperFactory;

class CheckAlbumAvailabilityAutomatedAction extends AbstractSequentialAutomatedAction
{
    private const string PLATFORM_INSTANCES = 'platform_instances';

    public function setUp(DataValidator $validator): void
    {
        $platformsWithAvailabilityCheck = [];
        foreach (Platform::cases() as $platform) {
            $helper = PlatformHelperFactory::get($platform, $this->coreServiceProvider);
            if ($helper->canGetAlbumAvailability()) {
                $platformsWithAvailabilityCheck[] = $platform->value;
            }
        }

        $dbService = $this->coreServiceProvider->getDatabaseService();
        $dbConn = $dbService->open();

        $limit = $validator->getFieldValue(self::LIMIT_OPTION, true);
        $firstId = $validator->getFieldValue(self::FIRST_ID_OPTION, true);

        $platformMarks = implode(',', array_pad([], count($platformsWithAvailabilityCheck), '?'));

        $sql = "SELECT `album_id`, `platform`, `platform_id` 
                    FROM `album_instances`
                    WHERE `platform` IN ({$platformMarks})
                        AND (`availability` = ? OR `last_availability_check` < DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))";
        $values = array_merge([], $platformsWithAvailabilityCheck, [PlatformAvailability::Unknown->value]);
        if ($firstId > 0) {
            $sql .= " AND `album_id` >= ?";
            $values[] = $firstId;
        }
        $sql .= " ORDER BY `album_id`";
        if ($limit > 0) {
            $sql .= " LIMIT {$limit}";
        }
        $results = $dbConn->get($sql, \PDO::FETCH_ASSOC, $values);
        if ($results === false) {
            throw new Exception("Unable to setup automated action");
        }

        $dbService->close($dbConn);

        $sessionData = [
            self::CURRENT_INDEX => 0,
            self::PLATFORM_INSTANCES => $results
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
        $platformInstances = $sessionData[self::PLATFORM_INSTANCES] ?? null;
        if (!is_array($platformInstances) || !is_int($currentIndex)) {
            return new AutomatedActionStepData(AutomatedActionStatus::failed, HttpStatusCodes::internalServerError, 'Invalid session data structure', AutomatedActionLogType::error);
        }

        $stepData = new AutomatedActionStepData();
        $stepData->totalItems = count($platformInstances);
        $stepData->currentItemNumber = $currentIndex + 1;

        if ($currentIndex >= count($platformInstances)) {
            $stepData->status = AutomatedActionStatus::complete;
            return $stepData;
        }

        $currentPlatformInstance = $platformInstances[$currentIndex];
        $currentPlatformId = $currentPlatformInstance['platform_id'];

        $dbService = $this->coreServiceProvider->getDatabaseService();
        $dbConn = $dbService->open();

        try {
            $albumName = $dbConn->getValue("SELECT `title` FROM `albums` WHERE `id` = ?", $currentPlatformInstance['album_id']);

            $platform = Platform::from($currentPlatformInstance['platform']);
            $platformHelper = PlatformHelperFactory::get($platform, $this->coreServiceProvider);
            $platformLabel = $platformHelper->getPlatform()->getLabel();

            $stepData->addLogLine("Looking for album information on {$platformLabel} (platform ID: {$currentPlatformId} - \"{$albumName}\")");

            $availability = $platformHelper->getAlbumAvailability($currentPlatformId);

            $sql = "UPDATE `album_instances`
                        SET `availability` = ?,
                            `last_availability_check` = CURRENT_TIMESTAMP()
                        WHERE `album_id` = ? AND `platform` = ?";
            $values = [$availability->value, $currentPlatformInstance['album_id'], $platform->value];
            if (!$dbConn->exec($sql, $values)) {
                throw new Exception('  Unable to update platform availability');
            }

            $logType = $availability == PlatformAvailability::NotAvailable ? AutomatedActionLogType::warning : AutomatedActionLogType::log;
            $stepData->addLogLine("  Status: {$availability->value}", $logType);

            $sessionData[self::CURRENT_INDEX] = $currentIndex + 1;
            $this->storeInSession(self::PROGRESS_DATA, $sessionData);
        } catch (Exception $e) {
            $stepData->status = AutomatedActionStatus::failed;
            if ($e instanceof PlatformHelperException && $e->statusCode !== null) {
                $stepData->httpStatusCode = $e->statusCode;
            }
            $stepData->addLogLine($e->getMessage(), AutomatedActionLogType::error);
        }

        $dbService->close($dbConn);

        return $stepData;
    }

    public function shutDown(): void
    {
        $this->removeFromSession(self::PROGRESS_DATA);
    }
}
