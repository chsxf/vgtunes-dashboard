<?php

declare(strict_types=1);

use Analytics\TimeFrame;
use chsxf\MFX\Attributes\PreRouteCallback;
use chsxf\MFX\Attributes\RequiredRequestMethod;
use chsxf\MFX\Attributes\Route;
use chsxf\MFX\DatabaseConnectionInstance;
use chsxf\MFX\DataValidator;
use chsxf\MFX\DataValidator\Fields\WithOptions;
use chsxf\MFX\DataValidator\FieldType;
use chsxf\MFX\DataValidator\Filters\ExistsInDB;
use chsxf\MFX\DataValidator\Filters\In;
use chsxf\MFX\HttpStatusCodes;
use chsxf\MFX\RequestMethod;
use chsxf\MFX\RequestResult;
use chsxf\MFX\Routers\BaseRouteProvider;
use chsxf\MFX\Services\IConfigService;
use chsxf\MFX\StringTools;
use PlatformHelpers\PlatformHelperFactory;
use PlatformHelpers\SteamGamePlatformHelper;

final class Album extends BaseRouteProvider
{
    private const SESS_ALBUM_DATA = 'album-data';

    private const string FUNCTION = 'function';
    private const string SAVED_DATA = 'saved_data';
    private const string NEW_DATA = 'new_data';
    private const string ALBUM_ID = 'album_id';

    private const string QUERY_FIELD = 'q';
    private const string PLATFORM_FIELD = 'platform';
    private const string CALLBACK_FIELD = 'callback';
    private const string TITLE_PREFIX_FIELD = 'title_prefix';
    private const string TITLE_FIELD = 'title';
    private const string ARTIST_NAME_FIELD = 'artist_name';
    private const string COVER_URL_FIELD = 'cover_url';
    private const string INSTANCES_FIELD = 'instances';
    private const string PLATFORM_ID_FIELD = 'platform_id';
    private const string LAST_FEATURED_FIELD = 'last_featured';
    private const string BACK_URL_FIELD = 'back_url';

    private const string PREVIOUS_SEARCHES = 'previous_searches';

    private const array SEARCH_QUERY_PARAMS_ADD = [
        self::CALLBACK_FIELD => '/Album/add',
        self::TITLE_PREFIX_FIELD => 'Add New Album',
        self::BACK_URL_FIELD => '/Album/show'
    ];

    #[Route, RequiredRequestMethod(RequestMethod::GET), RequiredRequestMethod(RequestMethod::POST)]
    public function add(): RequestResult
    {
        $reqService = $this->serviceProvider->getRequestService();
        $sessionService = $this->serviceProvider->getSessionService();

        if ($reqService->getRequestMethod() == RequestMethod::GET) {
            if (!empty($_REQUEST['new'])) {
                unset($sessionService[self::SESS_ALBUM_DATA]);
                return RequestResult::buildRedirectRequestResult('/Album/searchPlatform', self::SEARCH_QUERY_PARAMS_ADD);
            }
        } else if ($reqService->getRequestMethod() == RequestMethod::POST) {
            $validator = self::createSearchResultValidator();
            if (!$validator->validate($_POST)) {
                return RequestResult::buildStatusRequestResult();
            }

            if (isset($sessionService[self::SESS_ALBUM_DATA]) && $sessionService[self::SESS_ALBUM_DATA][self::FUNCTION] == __FUNCTION__) {
                $sessionAlbumData = $sessionService[self::SESS_ALBUM_DATA];
                $sessionAlbumData[self::INSTANCES_FIELD][$validator[self::PLATFORM_FIELD]] = [
                    self::PLATFORM_ID_FIELD => $validator[self::PLATFORM_ID_FIELD],
                    self::TITLE_FIELD => $validator[self::TITLE_FIELD],
                    self::ARTIST_NAME_FIELD => $validator[self::ARTIST_NAME_FIELD],
                    self::COVER_URL_FIELD => $validator[self::COVER_URL_FIELD],
                    self::SAVED_DATA => false
                ];

                if ($validator[self::PLATFORM_FIELD] == Platform::deezer->value) {
                    $sessionAlbumData[self::COVER_URL_FIELD] = $validator[self::COVER_URL_FIELD];
                }
            } else {
                $sessionAlbumData = [
                    self::FUNCTION => __FUNCTION__,
                    self::TITLE_FIELD => $validator[self::TITLE_FIELD],
                    self::ARTIST_NAME_FIELD => $validator[self::ARTIST_NAME_FIELD],
                    self::COVER_URL_FIELD => $validator[self::COVER_URL_FIELD],
                    self::INSTANCES_FIELD => [
                        $validator[self::PLATFORM_FIELD] => [
                            self::PLATFORM_ID_FIELD => $validator[self::PLATFORM_ID_FIELD],
                            self::TITLE_FIELD => $validator[self::TITLE_FIELD],
                            self::ARTIST_NAME_FIELD => $validator[self::ARTIST_NAME_FIELD],
                            self::COVER_URL_FIELD => $validator[self::COVER_URL_FIELD],
                            self::SAVED_DATA => false
                        ]
                    ]
                ];

                if ($validator[self::PLATFORM_FIELD] == Platform::steamGame->value) {
                    $heroCapsuleUrl = SteamGamePlatformHelper::getHeroCapsuleUrl($validator[self::PLATFORM_ID_FIELD], time());
                    if ($heroCapsuleUrl !== null) {
                        $sessionAlbumData[self::COVER_URL_FIELD] = $heroCapsuleUrl;
                    }
                }
            }

            foreach (Platform::cases() as $platform) {
                if (!array_key_exists($platform->value, $sessionAlbumData[self::INSTANCES_FIELD])) {
                    $helper = PlatformHelperFactory::get($platform, $this->serviceProvider);
                    $sessionAlbumData[self::INSTANCES_FIELD][$platform->value] = $helper->searchExactMatch($sessionAlbumData[self::TITLE_FIELD], $sessionAlbumData[self::ARTIST_NAME_FIELD]);
                }
            }

            $sessionService[self::SESS_ALBUM_DATA] = $sessionAlbumData;
            return RequestResult::buildRedirectRequestResult('/Album/show');
        }

        return RequestResult::buildStatusRequestResult(HttpStatusCodes::badRequest);
    }

    #[Route, RequiredRequestMethod(RequestMethod::POST)]
    public function edit(array $params): RequestResult
    {
        $sessionService = $this->serviceProvider->getSessionService();

        $dbService = $this->serviceProvider->getDatabaseService();
        $dbConn = $dbService->open();

        if (empty($params)) {
            return RequestResult::buildStatusRequestResult(HttpStatusCodes::badRequest);
        }

        $albumId = intval($params[0]);
        try {
            $albumDetails = self::getSavedAlbumDetails($dbConn, $this->serviceProvider->getConfigService(), $albumId);
        } catch (Exception) {
            return RequestResult::buildStatusRequestResult(HttpStatusCodes::badRequest);
        }

        $validator = self::createSearchResultValidator();
        if (!$validator->validate($_POST)) {
            return RequestResult::buildStatusRequestResult(HttpStatusCodes::badRequest);
        }

        if (
            isset($sessionService[self::SESS_ALBUM_DATA])
            && $sessionService[self::SESS_ALBUM_DATA][self::FUNCTION] == __FUNCTION__
            && !empty($sessionService[self::SESS_ALBUM_DATA][self::ALBUM_ID])
            && $sessionService[self::SESS_ALBUM_DATA][self::ALBUM_ID] == $albumId
        ) {
            $sessionAlbumData = $sessionService[self::SESS_ALBUM_DATA];
            $sessionAlbumData[self::INSTANCES_FIELD][$validator[self::PLATFORM_FIELD]] = [
                self::PLATFORM_ID_FIELD => $validator[self::PLATFORM_ID_FIELD],
                self::TITLE_FIELD => $validator[self::TITLE_FIELD],
                self::ARTIST_NAME_FIELD => $validator[self::ARTIST_NAME_FIELD],
                self::COVER_URL_FIELD => $validator[self::COVER_URL_FIELD],
                self::SAVED_DATA => false
            ];
        } else {
            $sessionAlbumData = [
                self::FUNCTION => __FUNCTION__,
                self::ALBUM_ID => $albumId,
                self::INSTANCES_FIELD => [
                    $validator[self::PLATFORM_FIELD] => [
                        self::PLATFORM_ID_FIELD => $validator[self::PLATFORM_ID_FIELD],
                        self::TITLE_FIELD => $validator[self::TITLE_FIELD],
                        self::ARTIST_NAME_FIELD => $validator[self::ARTIST_NAME_FIELD],
                        self::COVER_URL_FIELD => $validator[self::COVER_URL_FIELD],
                        self::SAVED_DATA => false
                    ]
                ]
            ];
        }

        $sessionService[self::SESS_ALBUM_DATA] = $sessionAlbumData;

        $redirectUrl = "/Album/show/{$albumId}";
        return RequestResult::buildRedirectRequestResult($redirectUrl);
    }

    private static function createEditSearchQueryParams(int $albumId): array
    {
        return [
            self::CALLBACK_FIELD => "/Album/edit/{$albumId}",
            self::TITLE_PREFIX_FIELD => 'Edit Album',
            self::BACK_URL_FIELD => "/Album/show/{$albumId}"
        ];
    }

    #[Route, RequiredRequestMethod(RequestMethod::GET)]
    public function searchPlatform(): RequestResult
    {
        $validator = new DataValidator();
        $validator->createField(self::CALLBACK_FIELD, FieldType::HIDDEN, required: false);
        $validator->createField(self::BACK_URL_FIELD, FieldType::HIDDEN, required: false);
        $validator->createField(self::TITLE_PREFIX_FIELD, FieldType::HIDDEN, required: false);
        $validator->createField(self::QUERY_FIELD, FieldType::TEXT, '', extras: ['class' => 'form-control']);
        $f = $validator->createField(self::PLATFORM_FIELD, FieldType::SELECT, Platform::deezer->value, required: false, extras: ['class' => 'form-select']);
        if ($f instanceof WithOptions) {
            $f->addOptions(Platform::PLATFORMS);
        }

        $previousSearches = [];
        if (!empty($_COOKIE[self::PREVIOUS_SEARCHES])) {
            try {
                $previousSearches = json_decode($_COOKIE[self::PREVIOUS_SEARCHES], flags: JSON_OBJECT_AS_ARRAY | JSON_THROW_ON_ERROR);
            } finally {
            }
        }

        $searchResults = null;
        $hasQuery = !empty(trim($_GET[self::QUERY_FIELD] ?? ''));
        if ($validator->validate($_GET, silent: !$hasQuery)) {
            if ($hasQuery) {
                if (!in_array($validator[self::QUERY_FIELD], $previousSearches)) {
                    if (count($previousSearches) >= 10) {
                        $previousSearches = array_slice($previousSearches, 1);
                    }
                    $previousSearches[] = $validator[self::QUERY_FIELD];
                    setcookie(self::PREVIOUS_SEARCHES, json_encode($previousSearches), time() + 86400 * 30, '/Album/searchPlatform');
                }

                try {
                    $platform = Platform::from($validator[self::PLATFORM_FIELD] ?? Platform::deezer->value);
                    $platformHelper = PlatformHelperFactory::get($platform, $this->serviceProvider);
                    $searchResults = $platformHelper->search($validator[self::QUERY_FIELD]);

                    if (!empty($searchResults)) {
                        $dbService = $this->serviceProvider->getDatabaseService();
                        $dbConn = $dbService->open();

                        $queryMarks = implode(',', array_pad([], count($searchResults), '?'));
                        $values = [$platformHelper->getPlatform()->value];
                        foreach ($searchResults as $result) {
                            $values[] = $result->platform_id;
                        }

                        $sql = "SELECT `platform_id`, COUNT(DISTINCT `album_id`)
                                    FROM `album_instances`
                                    WHERE `platform` = ? AND `platform_id` IN ({$queryMarks})
                                    GROUP BY `platform_id`";
                        $countByPlatformId = $dbConn->getPairs($sql, $values);
                        if ($countByPlatformId === false) {
                            throw new ErrorException('An error has occured while querying the database');
                        }

                        foreach ($searchResults as $result) {
                            if (!empty($countByPlatformId[$result->platform_id])) {
                                $result->existsInDatabase = true;
                            }
                        }
                    }
                } catch (Exception $e) {
                    trigger_error($e->getMessage());
                    return RequestResult::buildStatusRequestResult(HttpStatusCodes::internalServerError);
                }
            }
        }

        array_walk($previousSearches, function (&$search, $key) use ($validator) {
            $search = [
                'query' => $search,
                'validator' => self::createPreviousSearchValidator($validator, $search)
            ];
        });
        $previousSearches = array_reverse($previousSearches);

        $requestData = [
            'validator' => $validator,
            'search_result_validator' => self::createSearchResultValidator($validator[self::PLATFORM_FIELD]),
            'search_results' => $searchResults,
            'title_prefix' => $_REQUEST[self::TITLE_PREFIX_FIELD] ?? null,
            'callback' => $_REQUEST[self::CALLBACK_FIELD] ?? null,
            'previous_searches' => $previousSearches
        ];
        if (!empty($_REQUEST[self::BACK_URL_FIELD])) {
            $requestData[self::BACK_URL_FIELD] = $_REQUEST[self::BACK_URL_FIELD];
        }

        return new RequestResult(data: $requestData);
    }

    private static function createSearchResultValidator(?string $platform = null): DataValidator
    {
        $validator = new DataValidator();
        $validator->createField(self::PLATFORM_FIELD, FieldType::HIDDEN, $platform)
            ->addFilter(new In(array_keys(Platform::PLATFORMS)));
        $validator->createField(self::TITLE_FIELD, FieldType::HIDDEN);
        $validator->createField(self::ARTIST_NAME_FIELD, FieldType::HIDDEN);
        $validator->createField(self::COVER_URL_FIELD, FieldType::HIDDEN, required: false);
        $validator->createField(self::PLATFORM_ID_FIELD, FieldType::HIDDEN);
        return $validator;
    }

    private static function createPreviousSearchValidator(DataValidator $sourceValidator, string $query): DataValidator
    {
        $validator = new DataValidator();
        $validator->createField(self::PLATFORM_FIELD, FieldType::HIDDEN, $sourceValidator[self::PLATFORM_FIELD] ?? Platform::deezer->value);
        $validator->createField(self::CALLBACK_FIELD, FieldType::HIDDEN, $sourceValidator[self::CALLBACK_FIELD], required: false);
        $validator->createField(self::BACK_URL_FIELD, FieldType::HIDDEN, $sourceValidator[self::BACK_URL_FIELD], required: false);
        $validator->createField(self::TITLE_PREFIX_FIELD, FieldType::HIDDEN, $sourceValidator[self::TITLE_PREFIX_FIELD], required: false);
        $validator->createField(self::QUERY_FIELD, FieldType::HIDDEN, $query);
        return $validator;
    }

    private static function getSavedAlbumDetails(DatabaseConnectionInstance $dbConn, IConfigService $configService, int $albumId): array
    {
        $sql = "SELECT `al`.*, `ar`.`name` AS `artist_name`
                            FROM `albums` AS `al`
                            LEFT JOIN `artists` AS `ar`
                                ON `al`.`artist_id` = `ar`.`id`
                            WHERE `al`.`id` = ?";
        if (($albumRow = $dbConn->getRow($sql, \PDO::FETCH_ASSOC, $albumId)) === false) {
            throw new Exception('An error has occured while querying album details', E_USER_ERROR);
        }

        $sql = "SELECT `platform`, `platform_id` FROM `album_instances` WHERE `album_id` = ?";
        if (($instances = $dbConn->getIndexed($sql, self::PLATFORM_FIELD, \PDO::FETCH_ASSOC, $albumId)) === false) {
            throw new Exception("An error has occured while querying album's instances", E_USER_ERROR);
        }

        $sql = "SELECT MAX(`featured_at`) FROM `featured_albums` WHERE `album_id` = ?";
        if (($lastFeatured = $dbConn->getValue($sql, $albumId)) === false) {
            throw new Exception("An error has occured while querying album's last feature timestamp", E_USER_ERROR);
        }

        $albumDetails = $albumRow;
        $albumDetails[self::COVER_URL_FIELD] = sprintf("%s%s/cover_500.webp", $configService->getValue('covers.base_url'), $albumRow['slug']);
        $albumDetails[self::INSTANCES_FIELD] = array_map(fn($instance) => array_merge($instance, [self::SAVED_DATA => true]), $instances);
        $albumDetails[self::LAST_FEATURED_FIELD] = $lastFeatured;
        return $albumDetails;
    }

    #[Route, RequiredRequestMethod(RequestMethod::GET), PreRouteCallback('GlobalCallbacks::googleChartsPreRouteCallback')]
    public function show(array $params): RequestResult
    {
        $sessionService = $this->serviceProvider->getSessionService();

        $isSavedAlbum = !empty($params);

        $albumDetails = null;
        if ($isSavedAlbum) {
            $albumId = intval($params[0]);

            $dbService = $this->serviceProvider->getDatabaseService();
            $dbConn = $dbService->open();

            try {
                $albumDetails = self::getSavedAlbumDetails($dbConn, $this->serviceProvider->getConfigService(), $albumId);
            } catch (Exception) {
            }
        }

        if (isset($sessionService[self::SESS_ALBUM_DATA])) {
            $sessData = $sessionService[self::SESS_ALBUM_DATA];
            if ($sessData[self::FUNCTION] == 'add' && !$isSavedAlbum) {
                $albumDetails = $sessionService[self::SESS_ALBUM_DATA];
            } else if ($sessData[self::FUNCTION] == 'edit' && $sessData[self::ALBUM_ID] == $albumId) {
                $albumDetails[self::INSTANCES_FIELD] = array_merge($albumDetails[self::INSTANCES_FIELD], $sessData[self::INSTANCES_FIELD]);
                $albumDetails[self::NEW_DATA] = true;
            }
        }
        $albumDetails[self::NEW_DATA] ??= false;

        if (empty($albumDetails)) {
            return RequestResult::buildStatusRequestResult();
        }

        array_walk($albumDetails[self::INSTANCES_FIELD], function (&$instance, $platform) {
            if (!empty($instance[self::PLATFORM_ID_FIELD])) {
                $platformEnum = Platform::from($platform);
                $helper = PlatformHelperFactory::get($platformEnum, $this->serviceProvider);
                $instance['url'] = $helper->getLookUpURL($instance[self::PLATFORM_ID_FIELD]);
            }
        });

        $requestResultData = [
            'is_new' => !$isSavedAlbum,
            'album_details' => $albumDetails,
            'sanitized_title' => PlatformAlbum::cleanupAlbumTitle($albumDetails['title']),
            'platforms' => Platform::PLATFORMS,
            'search_query_params' => $isSavedAlbum ? self::createEditSearchQueryParams($albumId) : self::SEARCH_QUERY_PARAMS_ADD,
            'commit_url' => $isSavedAlbum ? "/Album/commit/{$albumId}" : "/Album/commit"
        ];

        $analyticsConfig = $this->serviceProvider->getConfigService()->getValue('analytics');
        if ($isSavedAlbum && !empty($analyticsConfig['enabled'])) {
            $this->serviceProvider->getScriptService()->add('/js/timeFrameSelector.js', defer: true);

            $validator = TimeFrame::buildAnalyticsTimeFrameSelectorValidator();
            $validator->validate($_GET, silent: true);

            $timeFrame = TimeFrame::tryFrom(intval($validator->getFieldValue(TimeFrame::TIMEFRAME_FIELD, true))) ?? TimeFrame::lastDays7;

            $url = "{$analyticsConfig['endpoint']}/DataExport.queryPage?";
            $queryParams = [
                'days' => $timeFrame->value,
                'domain' => $analyticsConfig['domain'],
                'path' => "/albums/{$albumDetails['slug']}/"
            ];
            $url .= http_build_query($queryParams);

            $requestResultData['analytics'] = [
                'access_key' => $analyticsConfig['access_key'],
                'graphDataURL' => $url,
                'validator' => $validator,
                'hAxisTitle' => $timeFrame === TimeFrame::realtime ? 'Time' : 'Date'
            ];
        }

        return new RequestResult(data: $requestResultData);
    }

    #[Route, RequiredRequestMethod(RequestMethod::POST)]
    public function commit(array $params): RequestResult
    {
        $sessionService = $this->serviceProvider->getSessionService();
        if (!isset($sessionService[self::SESS_ALBUM_DATA])) {
            return RequestResult::buildStatusRequestResult();
        }

        $dbService = $this->serviceProvider->getDatabaseService();
        $dbConn = $dbService->open();

        $dbConn->beginTransaction();

        try {
            $sessionAlbumData = $sessionService[self::SESS_ALBUM_DATA];

            if ($sessionAlbumData[self::FUNCTION] == 'add') {
                $hasAtLeastOnePlatformId = false;
                foreach ($sessionAlbumData[self::INSTANCES_FIELD] as $platform => $instanceData) {
                    if (!empty($instanceData)) {
                        $hasAtLeastOnePlatformId = true;
                        break;
                    }
                }
                if (!$hasAtLeastOnePlatformId) {
                    throw new Exception('At least one platform ID must be filled in');
                }

                $artistId = Artist::getOrCreateArtistId($dbConn, $sessionAlbumData[self::ARTIST_NAME_FIELD]);

                $slug = null;
                while ($slug === null) {
                    $candidateSlug = StringTools::generateRandomString(8, StringTools::CHARSET_ALPHANUMERIC_LC);
                    $sql = 'SELECT COUNT(`id`) FROM `albums` WHERE `slug` = ?';
                    $count = $dbConn->getValue($sql, $candidateSlug);
                    if ($count === false || $count === null) {
                        throw new Exception('A database error has occured');
                    }

                    if ($count == 0) {
                        $slug = $candidateSlug;
                    }
                }

                $sql = 'INSERT INTO `albums` (`slug`, `title`, `artist_id`) VALUE (?, ?, ?)';
                if (!$dbConn->exec($sql, $slug, $sessionAlbumData[self::TITLE_FIELD], $artistId)) {
                    throw new Exception('A database error has occured');
                }
                $albumId = $dbConn->lastInsertId();

                foreach ($sessionAlbumData[self::INSTANCES_FIELD] as $platform => $instanceData) {
                    if (!empty($instanceData)) {
                        $sql = 'INSERT INTO `album_instances` VALUE (?, ?, ?)';
                        if (!$dbConn->exec($sql, $albumId, $platform, $instanceData[self::PLATFORM_ID_FIELD])) {
                            throw new Exception('A database error has occured');
                        }
                    }
                }

                $coverSizes = CoverProcessor::getProcessedCovers($sessionAlbumData[self::COVER_URL_FIELD]);
                $outputPath = $this->serviceProvider->getConfigService()->getValue('covers.output_path');
                $outputPath .= "/{$slug}";
                if (mkdir($outputPath, 0770, true) === false) {
                    throw new Exception('Unable to create the covers folder');
                }
                foreach ($coverSizes as $size => $coverImage) {
                    $ext = is_int($size) ? 'webp' : 'jpg';
                    $filePath = "{$outputPath}/cover_{$size}.{$ext}";
                    if (file_put_contents($filePath, $coverImage) === false) {
                        throw new Exception('Unable to write cover file');
                    }
                }

                trigger_notif("The album has been successfully added with the slug '{$slug}'");
            } else if ($sessionAlbumData[self::FUNCTION] == 'edit') {
                $validator = new DataValidator();
                $validator->createField('0', FieldType::POSITIVE_INTEGER)
                    ->addFilter(new ExistsInDB('albums', 'id', $dbConn));

                if (empty($params) || !$validator->validate($params, silent: true)) {
                    throw new Exception("Missing or invalid album Id");
                }

                $albumId = $validator['0'];
                if ($sessionAlbumData[self::ALBUM_ID] != $albumId) {
                    throw new Exception("Inconsistent album Id");
                }

                foreach ($sessionAlbumData[self::INSTANCES_FIELD] as $platform => $instanceData) {
                    if (!empty($instanceData) && $instanceData[self::SAVED_DATA] === false) {
                        $sql = 'INSERT INTO `album_instances` VALUE (?, ?, ?) ON DUPLICATE KEY UPDATE `platform_id` = ?';
                        if (!$dbConn->exec($sql, $albumId, $platform, $instanceData[self::PLATFORM_ID_FIELD], $instanceData[self::PLATFORM_ID_FIELD])) {
                            throw new Exception('A database error has occured');
                        }
                    }
                }

                trigger_notif("The album has been successfully edited");
            }

            $dbConn->commit();
            unset($sessionService[self::SESS_ALBUM_DATA]);
            return RequestResult::buildRedirectRequestResult("/Album/show/{$albumId}");
        } catch (Exception $e) {
            $dbConn->rollBack();
            trigger_error($e->getMessage());
            return RequestResult::buildRedirectRequestResult("/Album/show");
        }
    }
}
