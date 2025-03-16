<?php

use Analytics\TimeFrame;
use chsxf\MFX\Attributes\PreRouteCallback;
use chsxf\MFX\Attributes\RequiredRequestMethod;
use chsxf\MFX\Attributes\Route;
use chsxf\MFX\DatabaseConnectionInstance;
use chsxf\MFX\DataValidator;
use chsxf\MFX\DataValidator\FieldType;
use chsxf\MFX\DataValidator\Filters\ExistsInDB;
use chsxf\MFX\HttpStatusCodes;
use chsxf\MFX\RequestMethod;
use chsxf\MFX\RequestResult;
use chsxf\MFX\Routers\BaseRouteProvider;
use chsxf\MFX\Services\IConfigService;
use chsxf\MFX\StringTools;

final class Artist extends BaseRouteProvider
{
    private const string ID_FIELD = 'id';
    private const string NAME_FIELD = 'name';

    #[Route, RequiredRequestMethod(RequestMethod::GET), PreRouteCallback('GlobalCallbacks::googleChartsPreRouteCallback')]
    public function show(array $params): RequestResult
    {
        $artistId = intval($params[0]);

        $dbService = $this->serviceProvider->getDatabaseService();
        $dbConn = $dbService->open();

        try {
            $artistDetails = self::getArtistDetails($dbConn, $this->serviceProvider->getConfigService(), $artistId);
            $validator = self::createValidator($dbConn, $artistDetails);
        } catch (Exception) {
        }

        if (empty($artistDetails)) {
            return RequestResult::buildStatusRequestResult();
        }

        $requestResultData = [
            'artist_details' => $artistDetails,
            'validator' => $validator
        ];

        $analyticsConfig = $this->serviceProvider->getConfigService()->getValue('analytics');
        if (!empty($analyticsConfig['enabled'])) {
            $this->serviceProvider->getScriptService()->add('/js/timeFrameSelector.js', defer: true);

            $analyticsValidator = TimeFrame::buildAnalyticsTimeFrameSelectorValidator();
            $analyticsValidator->validate($_GET, silent: true);

            $timeFrame = TimeFrame::tryFrom(intval($analyticsValidator->getFieldValue(TimeFrame::TIMEFRAME_FIELD, true))) ?? TimeFrame::lastDays7;

            $url = "{$analyticsConfig['endpoint']}/DataExport.queryPage?";
            $queryParams = [
                'days' => $timeFrame->value,
                'domain' => $analyticsConfig['domain'],
                'path' => "/artists/{$artistDetails['slug']}/"
            ];
            $url .= http_build_query($queryParams);

            $requestResultData['analytics'] = [
                'access_key' => $analyticsConfig['access_key'],
                'graphDataURL' => $url,
                'validator' => $analyticsValidator,
                'hAxisTitle' => $timeFrame === TimeFrame::realtime ? 'Time' : 'Date'
            ];
        }

        return new RequestResult(data: $requestResultData);
    }

    private static function getArtistDetails(DatabaseConnectionInstance $dbConn, IConfigService $configService, int $artistId): array
    {
        $sql = "SELECT * FROM `artists` WHERE `id` = ?";
        if (($artistRow = $dbConn->getRow($sql, \PDO::FETCH_ASSOC, $artistId)) === false) {
            throw new Exception('An error has occured while querying artist details', E_USER_ERROR);
        }
        return $artistRow;
    }

    private static function createValidator(?DatabaseConnectionInstance $dbConn, ?array $artistDetails = null): DataValidator
    {
        $validator = new DataValidator();
        if ($dbConn !== null) {
            $validator->createField(self::ID_FIELD, FieldType::POSITIVE_INTEGER, $artistDetails[self::ID_FIELD] ?? null)
                ->addFilter(new ExistsInDB('artists', 'id', $dbConn));
        }
        $validator->createField(self::NAME_FIELD, FieldType::TEXT, $artistDetails[self::NAME_FIELD] ?? null, extras: ['class' => 'form-control']);
        return $validator;
    }

    public static function getOrCreateArtistId(DatabaseConnectionInstance $dbConn, string $artistName): int
    {
        $sql = "SELECT `id` FROM `artists` WHERE `name` = ?";
        if (($artistId = $dbConn->getValue($sql, $artistName)) === false) {
            $slug = null;
            while ($slug === null) {
                $candidateSlug = StringTools::generateRandomString(8);
                $sql = 'SELECT COUNT(`id`) FROM `artists` WHERE `slug` = ?';
                $count = $dbConn->getValue($sql, $candidateSlug);
                if ($count === false || $count === null) {
                    throw new Exception("A database error has occured");
                }

                if ($count == 0) {
                    $slug = $candidateSlug;
                }
            }

            $sql = "INSERT INTO `artists` (`name`, `slug`) VALUE (?, ?)";
            if ($dbConn->exec($sql, $artistName, $slug) === false) {
                throw new Exception('A database error has occured');
            }
            $artistId = $dbConn->lastInsertId();
        }
        return $artistId;
    }

    #[Route, RequiredRequestMethod(RequestMethod::GET), RequiredRequestMethod(RequestMethod::POST)]
    public function add(): RequestResult
    {
        $requestService = $this->serviceProvider->getRequestService();

        $validator = self::createValidator(null);

        if ($requestService->getRequestMethod() == RequestMethod::POST) {
            if (!$validator->validate($_POST)) {
                return RequestResult::buildStatusRequestResult(HttpStatusCodes::badRequest);
            }

            $dbService = $this->serviceProvider->getDatabaseService();
            $dbConn = $dbService->open();

            $dbConn->beginTransaction();

            try {
                $artistId = self::getOrCreateArtistId($dbConn, $validator[self::NAME_FIELD]);
                $dbConn->commit();
                return RequestResult::buildRedirectRequestResult("/Artist/show/{$artistId}");
            } catch (Exception $e) {
                $dbConn->rollBack();
                return RequestResult::buildStatusRequestResult(HttpStatusCodes::internalServerError);
            }
        } else {
            return new RequestResult(data: ['validator' => $validator]);
        }
    }

    #[Route, RequiredRequestMethod(RequestMethod::POST)]
    public function edit(): RequestResult
    {
        $dbService = $this->serviceProvider->getDatabaseService();
        $dbConn = $dbService->open();

        $validator = self::createValidator($dbConn);
        if ($validator->validate($_POST)) {
            $sql = "UPDATE `artists` SET `name` = ? WHERE `id` = ?";
            if (!$dbConn->exec($sql, $validator[self::NAME_FIELD], $validator[self::ID_FIELD])) {
                return RequestResult::buildStatusRequestResult(HttpStatusCodes::internalServerError);
            }
            return RequestResult::buildRedirectRequestResult("/Artist/show/{$validator[self::ID_FIELD]}");
        } else {
            return RequestResult::buildStatusRequestResult(HttpStatusCodes::badRequest);
        }
    }
}
