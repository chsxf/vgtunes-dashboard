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
    private const string DATA_STATUS = 'data_status';
    private const string NEW_DATA = 'new_data';
    private const string ALBUM_ID = 'album_id';

    private const string QUERY_FIELD = 'q';
    private const string PLATFORM_FIELD = 'platform';
    private const string CALLBACK_FIELD = 'callback';
    private const string TITLE_PREFIX_FIELD = 'title_prefix';
    private const string TITLE_FIELD = 'title';
    private const string ARTISTS_FIELD = 'artists';
    private const string COVER_URL_FIELD = 'cover_url';
    private const string INSTANCES_FIELD = 'instances';
    private const string PLATFORM_ID_FIELD = 'platform_id';
    private const string LAST_FEATURED_FIELD = 'last_featured';
    private const string BACK_URL_FIELD = 'back_url';
    private const string START_AT_FIELD = 'start_at';

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

            $decodedArtists = json_decode($validator[self::ARTISTS_FIELD]);

            if (isset($sessionService[self::SESS_ALBUM_DATA]) && $sessionService[self::SESS_ALBUM_DATA][self::FUNCTION] == __FUNCTION__) {
                $sessionAlbumData = $sessionService[self::SESS_ALBUM_DATA];
                $sessionAlbumData[self::INSTANCES_FIELD][$validator[self::PLATFORM_FIELD]] = [
                    self::PLATFORM_ID_FIELD => $validator[self::PLATFORM_ID_FIELD],
                    self::TITLE_FIELD => $validator[self::TITLE_FIELD],
                    self::ARTISTS_FIELD => $decodedArtists,
                    self::COVER_URL_FIELD => $validator[self::COVER_URL_FIELD],
                    self::DATA_STATUS => AlbumDataStatus::new
                ];

                if ($validator[self::PLATFORM_FIELD] == Platform::deezer->value) {
                    $sessionAlbumData[self::COVER_URL_FIELD] = $validator[self::COVER_URL_FIELD];
                }
            } else {
                $initialPlatform = Platform::from($validator[self::PLATFORM_FIELD]);
                $helper = PlatformHelperFactory::get($initialPlatform, $this->serviceProvider);
                $platformAlbumDetails = $helper->getAlbumDetails($validator[self::PLATFORM_ID_FIELD]);
                if ($platformAlbumDetails === false) {
                    return RequestResult::buildStatusRequestResult(HttpStatusCodes::internalServerError);
                }

                $title = $validator[self::TITLE_FIELD];
                $artists = $decodedArtists;
                if ($platformAlbumDetails !== null) {
                    $title = $platformAlbumDetails->title;
                    $artists = $platformAlbumDetails->artists;
                }

                $sessionAlbumData = [
                    self::FUNCTION => __FUNCTION__,
                    self::TITLE_FIELD => $title,
                    self::ARTISTS_FIELD => $artists,
                    self::COVER_URL_FIELD => $validator[self::COVER_URL_FIELD],
                    self::INSTANCES_FIELD => [
                        $validator[self::PLATFORM_FIELD] => [
                            self::PLATFORM_ID_FIELD => $validator[self::PLATFORM_ID_FIELD],
                            self::TITLE_FIELD => $title,
                            self::ARTISTS_FIELD => $artists,
                            self::COVER_URL_FIELD => $validator[self::COVER_URL_FIELD],
                            self::DATA_STATUS => AlbumDataStatus::new
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
                    if (($exactMatch = $helper->searchExactMatch($sessionAlbumData[self::TITLE_FIELD], $sessionAlbumData[self::ARTISTS_FIELD])) !== null) {
                        $exactMatch[self::DATA_STATUS] = AlbumDataStatus::new;
                        $sessionAlbumData[self::INSTANCES_FIELD][$platform->value] = $exactMatch;
                    }
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
            self::getSavedAlbumDetails($dbConn, $this->serviceProvider->getConfigService(), $albumId);
        } catch (Exception) {
            return RequestResult::buildStatusRequestResult(HttpStatusCodes::badRequest);
        }

        $validator = self::createSearchResultValidator();
        if (!$validator->validate($_POST)) {
            return RequestResult::buildStatusRequestResult(HttpStatusCodes::badRequest);
        }

        $decodedArtists = json_decode($validator[self::ARTISTS_FIELD]);

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
                self::ARTISTS_FIELD => $decodedArtists,
                self::COVER_URL_FIELD => $validator[self::COVER_URL_FIELD],
                self::DATA_STATUS => AlbumDataStatus::new
            ];
        } else {
            $sessionAlbumData = [
                self::FUNCTION => __FUNCTION__,
                self::ALBUM_ID => $albumId,
                self::INSTANCES_FIELD => [
                    $validator[self::PLATFORM_FIELD] => [
                        self::PLATFORM_ID_FIELD => $validator[self::PLATFORM_ID_FIELD],
                        self::TITLE_FIELD => $validator[self::TITLE_FIELD],
                        self::ARTISTS_FIELD => $decodedArtists,
                        self::COVER_URL_FIELD => $validator[self::COVER_URL_FIELD],
                        self::DATA_STATUS => AlbumDataStatus::new
                    ]
                ]
            ];
        }

        $sessionService[self::SESS_ALBUM_DATA] = $sessionAlbumData;

        $redirectUrl = "/Album/show/{$albumId}";
        return RequestResult::buildRedirectRequestResult($redirectUrl);
    }

    #[Route, RequiredRequestMethod(RequestMethod::POST)]
    public function removePlatform(): RequestResult
    {
        $dbService = $this->serviceProvider->getDatabaseService();
        $dbConn = $dbService->open();

        $validator = new DataValidator();
        $validator->createField(self::ALBUM_ID, FieldType::POSITIVE_INTEGER, required: false)
            ->addFilter(new ExistsInDB('albums', 'id', $dbConn));
        $validator->createField(self::PLATFORM_FIELD, FieldType::TEXT)
            ->addFilter(new In(array_keys(Platform::PLATFORMS)));

        if (!$validator->validate($_POST)) {
            return RequestResult::buildStatusRequestResult(HttpStatusCodes::badRequest);
        }

        $albumId = $validator->getFieldValue(self::ALBUM_ID, true);
        $isNew = empty($albumId);

        if ($isNew) {
            $sessionService = $this->serviceProvider->getSessionService();
            if (!isset($sessionService[self::SESS_ALBUM_DATA])) {
                return RequestResult::buildStatusRequestResult(HttpStatusCodes::badRequest);
            }

            $sessionAlbumData = $sessionService[self::SESS_ALBUM_DATA];
            unset($sessionAlbumData[self::INSTANCES_FIELD][$validator[self::PLATFORM_FIELD]]);
            $sessionService[self::SESS_ALBUM_DATA] = $sessionAlbumData;

            return RequestResult::buildRedirectRequestResult('/Album/show');
        } else {
            $sql = "DELETE FROM `album_instances` WHERE `album_id` = ? AND `platform` = ?";
            if ($dbConn->exec($sql, $albumId, $validator[self::PLATFORM_FIELD]) === false) {
                return RequestResult::buildStatusRequestResult(HttpStatusCodes::badRequest);
            }
            return RequestResult::buildRedirectRequestResult("/Album/show/{$albumId}");
        }
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
        $validator->createField(self::CALLBACK_FIELD, FieldType::TEXT, required: false);
        $validator->createField(self::BACK_URL_FIELD, FieldType::TEXT, required: false);
        $validator->createField(self::START_AT_FIELD, FieldType::POSITIVEZERO_INTEGER, defaultValue: 0, required: false);
        $validator->createField(self::TITLE_PREFIX_FIELD, FieldType::TEXT, required: false);
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

        $nextPageStartAt = null;

        $searchResults = null;
        $hasQuery = !empty(trim($_GET[self::QUERY_FIELD] ?? ''));
        if ($validator->validate($_GET, silent: !$hasQuery)) {
            if ($hasQuery) {
                $searchQuery = $validator[self::QUERY_FIELD];

                if (count($previousSearches) >= 10) {
                    $previousSearches = array_slice($previousSearches, 1);
                }
                $previousSearches = array_filter($previousSearches, fn($item) => strcasecmp($item, $searchQuery) != 0);
                $previousSearches[] = $searchQuery;
                setcookie(self::PREVIOUS_SEARCHES, json_encode($previousSearches), time() + 86400 * 30, '/Album/searchPlatform');

                try {
                    $platform = Platform::from($validator[self::PLATFORM_FIELD] ?? Platform::deezer->value);
                    $platformHelper = PlatformHelperFactory::get($platform, $this->serviceProvider);

                    $searchStartAt = $platformHelper->supportsPagination() ? intval($validator[self::START_AT_FIELD]) : null;
                    $searchResults = $platformHelper->search($searchQuery, $searchStartAt);

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

                        if ($platformHelper->supportsPagination()) {
                            $nextPageStartAt = $platformHelper->nextPageStart();
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
        if ($nextPageStartAt !== null) {
            $requestData['next_page_validator'] = self::createOtherSearchPageValidator($validator, $nextPageStartAt);
        }
        if (!empty($_REQUEST[self::BACK_URL_FIELD])) {
            $requestData[self::BACK_URL_FIELD] = $_REQUEST[self::BACK_URL_FIELD];
        }

        return new RequestResult(data: $requestData);
    }

    private static function createSearchResultValidator(?string $platform = null): DataValidator
    {
        $validator = new DataValidator();
        $validator->createField(self::PLATFORM_FIELD, FieldType::TEXT, $platform)
            ->addFilter(new In(array_keys(Platform::PLATFORMS)));
        $validator->createField(self::TITLE_FIELD, FieldType::TEXT);
        $validator->createField(self::ARTISTS_FIELD, FieldType::TEXT);
        $validator->createField(self::COVER_URL_FIELD, FieldType::TEXT, required: false);
        $validator->createField(self::PLATFORM_ID_FIELD, FieldType::TEXT);
        return $validator;
    }

    private static function createPreviousSearchValidator(DataValidator $sourceValidator, string $query): DataValidator
    {
        $validator = new DataValidator();
        $validator->createField(self::PLATFORM_FIELD, FieldType::TEXT, $sourceValidator[self::PLATFORM_FIELD] ?? Platform::deezer->value);
        $validator->createField(self::CALLBACK_FIELD, FieldType::TEXT, $sourceValidator[self::CALLBACK_FIELD], required: false);
        $validator->createField(self::BACK_URL_FIELD, FieldType::TEXT, $sourceValidator[self::BACK_URL_FIELD], required: false);
        $validator->createField(self::TITLE_PREFIX_FIELD, FieldType::TEXT, $sourceValidator[self::TITLE_PREFIX_FIELD], required: false);
        $validator->createField(self::QUERY_FIELD, FieldType::TEXT, $query);
        return $validator;
    }

    private static function createOtherSearchPageValidator(DataValidator $sourceValidator, int $startAt): DataValidator
    {
        $validator = new DataValidator();
        $validator->createField(self::PLATFORM_FIELD, FieldType::TEXT, $sourceValidator[self::PLATFORM_FIELD] ?? Platform::deezer->value);
        $validator->createField(self::CALLBACK_FIELD, FieldType::TEXT, $sourceValidator[self::CALLBACK_FIELD], required: false);
        $validator->createField(self::BACK_URL_FIELD, FieldType::TEXT, $sourceValidator[self::BACK_URL_FIELD], required: false);
        $validator->createField(self::START_AT_FIELD, FieldType::POSITIVEZERO_INTEGER, $startAt, required: false);
        $validator->createField(self::TITLE_PREFIX_FIELD, FieldType::TEXT, $sourceValidator[self::TITLE_PREFIX_FIELD], required: false);
        $validator->createField(self::QUERY_FIELD, FieldType::TEXT, $sourceValidator[self::QUERY_FIELD]);
        return $validator;
    }

    private static function getSavedAlbumDetails(DatabaseConnectionInstance $dbConn, IConfigService $configService, int $albumId): array
    {
        $sql = "SELECT * FROM `albums` WHERE `id` = ?";
        if (($albumRow = $dbConn->getRow($sql, \PDO::FETCH_ASSOC, $albumId)) === false) {
            throw new Exception('An error has occured while querying album details', E_USER_ERROR);
        }

        $sql = "SELECT `platform`, `platform_id` FROM `album_instances` WHERE `album_id` = ?";
        if (($instances = $dbConn->getIndexed($sql, self::PLATFORM_FIELD, \PDO::FETCH_ASSOC, $albumId)) === false) {
            throw new Exception("An error has occured while querying album's instances", E_USER_ERROR);
        }

        $sql = "SELECT `ar`.`name`
                    FROM `album_artists` AS `aa`
                    LEFT JOIN `artists` AS `ar`
                        ON `aa`.`artist_id` = `ar`.`id`
                    WHERE `aa`.`album_id` = ?";
        if (($artists = $dbConn->getColumn($sql, $albumId)) === false) {
            throw new Exception("An error has occured while querying album's artists", E_USER_ERROR);
        }

        $sql = "SELECT MAX(`featured_at`) FROM `featured_albums` WHERE `album_id` = ?";
        if (($lastFeatured = $dbConn->getValue($sql, $albumId)) === false) {
            throw new Exception("An error has occured while querying album's last feature timestamp", E_USER_ERROR);
        }

        $albumDetails = $albumRow;
        $albumDetails[self::COVER_URL_FIELD] = sprintf("%s%s/cover_500.webp", $configService->getValue('covers.base_url'), $albumRow['slug']);
        $albumDetails[self::ARTISTS_FIELD] = $artists;
        $albumDetails[self::INSTANCES_FIELD] = array_map(fn($instance) => array_merge($instance, [self::DATA_STATUS => AlbumDataStatus::saved]), $instances);
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

                $artistsIds = [];
                foreach ($sessionAlbumData[self::ARTISTS_FIELD] as $artist) {
                    $artistsIds[] = Artist::getOrCreateArtistId($dbConn, $artist);
                }

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

                $featureFlags = ['bandcamp', 'steam', 'multi_artists'];

                $sql = 'INSERT INTO `albums` (`slug`, `title`, `feature_flags`) VALUE (?, ?, ?)';
                if (!$dbConn->exec($sql, $slug, $sessionAlbumData[self::TITLE_FIELD], implode(',', $featureFlags))) {
                    throw new Exception('A database error has occured');
                }
                $albumId = $dbConn->lastInsertId();

                $currentOrder = 1;
                foreach ($artistsIds as $artistId) {
                    $sql = "INSERT INTO `album_artists` VALUE (?, ?, ?)";
                    if ((!$dbConn->exec($sql, $albumId, $artistId, $currentOrder))) {
                        throw new Exception('A database error has occured');
                    }
                    $currentOrder++;
                }

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
                    if (!empty($instanceData)) {
                        switch ($instanceData[self::DATA_STATUS]) {
                            case AlbumDataStatus::new:
                                $sql = 'INSERT INTO `album_instances` VALUE (?, ?, ?) ON DUPLICATE KEY UPDATE `platform_id` = ?';
                                if (!$dbConn->exec($sql, $albumId, $platform, $instanceData[self::PLATFORM_ID_FIELD], $instanceData[self::PLATFORM_ID_FIELD])) {
                                    throw new Exception('A database error has occured');
                                }
                                break;

                            case AlbumDataStatus::removed:
                                $sql = 'DELETE FROM `album_instances` WHERE `album_id` = ? AND `platform` = ?';
                                if (!$dbConn->exec($sql, $albumId, $platform)) {
                                    throw new Exception('A database error has occured');
                                }
                                break;
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
