<?php

namespace AutomatedActions;

use chsxf\MFX\DataValidator;
use chsxf\MFX\HttpStatusCodes;
use Exception;
use JsonException;
use PlatformHelpers\PlatformHelperException;

class SteamProductsUpdater extends AbstractAutomatedAction
{
    private const string TYPES_KEY = 'types';
    private const string CURRENT_TYPE_KEY = 'current_type';
    private const string PROGRESS_KEY = 'progress';
    private const string LAST_APPID_KEY = 'last_appid';
    private const string LAST_UPDATE_KEY = 'last_update';

    private const string STEAM_GETAPPLIST_URL = 'https://api.steampowered.com/IStoreService/GetAppList/v1/';

    public function setUp(DataValidator $validator): void
    {
        $dbService = $this->coreServiceProvider->getDatabaseService();
        $dbConn = $dbService->open();

        $types = SteamProductType::cases();

        $sql = "SELECT `type`, UNIX_TIMESTAMP(MAX(`last_update`))
                    FROM `steam_products`
                    GROUP BY `type`";
        if (($lastUpdates = $dbConn->getPairs($sql)) === false) {
            throw new Exception('Unable to fetch last update times');
        }

        $dbService->close($dbConn);

        $progress = [];
        foreach ($types as $type) {
            $progress[$type->value] = [
                self::LAST_APPID_KEY => 0,
                self::LAST_UPDATE_KEY => $lastUpdates[$type->value] ?? 0
            ];
        }

        $sessionData = [
            self::TYPES_KEY => $types,
            self::CURRENT_TYPE_KEY => reset($types),
            self::PROGRESS_KEY => $progress
        ];
        $this->storeInSession(self::PROGRESS_DATA, $sessionData);
    }

    public function proceedWithNextStep(): AutomatedActionStepData
    {
        $sessionData = $this->getFromSession(self::PROGRESS_DATA);
        if ($sessionData === null) {
            return new AutomatedActionStepData(AutomatedActionStatus::failed, HttpStatusCodes::internalServerError, 'Unable to retrieve session data', AutomatedActionLogType::error);
        }

        $currentType = $sessionData[self::CURRENT_TYPE_KEY];
        $currentTypeIndex = array_search($currentType, $sessionData[self::TYPES_KEY]);
        $currentTypeProgress = $sessionData[self::PROGRESS_KEY][$currentType->value];

        $stepData = new AutomatedActionStepData();
        $stepData->totalItems = 3;
        $stepData->currentItemNumber = $currentTypeIndex + 1;
        $stepData->addLogLine("Fetching next entries for product type '{$currentType->value}'...");

        $http_query = [
            'key' => $this->coreServiceProvider->getConfigService()->getValue('steam.api_key'),
            'max_results' => 50_000,
            'last_appid' => $currentTypeProgress[self::LAST_APPID_KEY],
            'include_software' => 'false',
            'include_videos' => 'false',
            'include_hardware' => 'false'
        ];
        if (($lastUpdate = $currentTypeProgress[self::LAST_UPDATE_KEY]) > 0) {
            $http_query['if_modified_since'] = $lastUpdate;
        }
        switch ($sessionData[self::CURRENT_TYPE_KEY]) {
            case SteamProductType::game:
                $http_query['include_games'] = 'true';
                $http_query['include_dlc'] = 'false';
                break;
            case SteamProductType::dlc:
                $http_query['include_games'] = 'false';
                $http_query['include_dlc'] = 'true';
                break;
            default:
                $http_query['include_games'] = 'false';
                $http_query['include_dlc'] = 'false';
                break;
        }

        $url = sprintf("%s?%s", self::STEAM_GETAPPLIST_URL, http_build_query($http_query));

        $dbService = $this->coreServiceProvider->getDatabaseService();
        $dbConn = $dbService->open();

        $dbConn->beginTransaction();

        try {
            $requestResult = self::request($url);
            $stepData->addLogLine(sprintf("  Fetched %d entries", count($requestResult['apps'])));

            foreach ($requestResult['apps'] as $app) {
                $lastModification = $app['last_modified'] ?? 1;
                $sql = "INSERT INTO `steam_products` VALUE (?, ?, ?, FROM_UNIXTIME(?))
                            ON DUPLICATE KEY UPDATE `name` = ?, `last_update` = FROM_UNIXTIME(?)";
                $values = [$app['appid'], $app['name'], $currentType->value, $lastModification, $app['name'], $lastModification];
                if ($dbConn->exec($sql, $values) === false) {
                    throw new Exception("Unable to insert new appid - parameters: " . print_r($values, true));
                }
            }

            $dbConn->commit();

            if ($requestResult['have_more_results'] === true) {
                $sessionData[self::PROGRESS_KEY][$currentType->value][self::LAST_APPID_KEY] = $requestResult[self::LAST_APPID_KEY];
                $this->storeInSession(self::PROGRESS_DATA, $sessionData);
                $stepData->addLogLine('  More entries are available');
            } else {
                $stepData->addLogLine('  No more entries are available');
                switch ($currentType) {
                    case SteamProductType::game:
                        $sessionData[self::CURRENT_TYPE_KEY] = SteamProductType::dlc;
                        $this->storeInSession(self::PROGRESS_DATA, $sessionData);
                        break;
                    case SteamProductType::dlc:
                        $sessionData[self::CURRENT_TYPE_KEY] = SteamProductType::other;
                        $this->storeInSession(self::PROGRESS_DATA, $sessionData);
                        break;
                    default:
                        $stepData->status = AutomatedActionStatus::complete;
                        break;
                }
            }
        } catch (Exception $e) {
            if ($dbConn->inTransaction()) {
                $dbConn->rollBack();
            }
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

    private static function request(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json'
            ]
        ]);
        $result = curl_exec($ch);
        if ($result === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new PlatformHelperException($error);
        } else if (($http_status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE)) !== 200) {
            $httpStatusCode = HttpStatusCodes::tryFrom($http_status);
            throw new PlatformHelperException("Server responded with HTTP status code {$http_status}", $httpStatusCode);
        }
        curl_close($ch);

        try {
            $decodedJson = json_decode($result, JSON_THROW_ON_ERROR | JSON_OBJECT_AS_ARRAY);
        } catch (JsonException $e) {
            throw new PlatformHelperException('An error has occuped while parsing search results.', previous: $e);
        }

        return $decodedJson['response'];
    }
}
