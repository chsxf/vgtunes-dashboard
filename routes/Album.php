<?php

declare(strict_types=1);

use chsxf\MFX\Attributes\RequiredRequestMethod;
use chsxf\MFX\Attributes\Route;
use chsxf\MFX\DataValidator;
use chsxf\MFX\DataValidator\Fields\WithOptions;
use chsxf\MFX\DataValidator\FieldType;
use chsxf\MFX\DataValidator\Filters\In;
use chsxf\MFX\HttpStatusCodes;
use chsxf\MFX\RequestMethod;
use chsxf\MFX\RequestResult;
use chsxf\MFX\Routers\BaseRouteProvider;
use chsxf\MFX\StringTools;
use PlatformHelpers\PlatformHelperFactory;

final class Album extends BaseRouteProvider
{
    private const SESS_ALBUM_DATA = 'album-data';

    private const string QUERY_FIELD = 'q';
    private const string PLATFORM_FIELD = 'platform';
    private const string CALLBACK_FIELD = 'callback';
    private const string TITLE_PREFIX_FIELD = 'title_prefix';
    private const string TITLE_FIELD = 'title';
    private const string ARTIST_NAME_FIELD = 'artist_name';
    private const string COVER_URL_FIELD = 'cover_url';
    private const string INSTANCES_FIELD = 'instances';
    private const string PLATFORM_ID_FIELD = 'platform_id';
    private const string PREVIOUS_SEARCHES = 'previous_searches';

    private const array SEARCH_QUERY_PARAMS = [self::CALLBACK_FIELD => '/Album/add', self::TITLE_PREFIX_FIELD => 'Add New Album'];

    #[Route, RequiredRequestMethod(RequestMethod::GET), RequiredRequestMethod(RequestMethod::POST)]
    public function add(): RequestResult
    {
        $reqService = $this->serviceProvider->getRequestService();
        $sessionService = $this->serviceProvider->getSessionService();

        if ($reqService->getRequestMethod() == RequestMethod::GET) {
            if (!empty($_REQUEST['new'])) {
                unset($sessionService[self::SESS_ALBUM_DATA]);
                return RequestResult::buildRedirectRequestResult('/Album/searchPlatform', self::SEARCH_QUERY_PARAMS);
            }
        } else if ($reqService->getRequestMethod() == RequestMethod::POST) {
            $validator = self::createSearchResultValidator();
            if (!$validator->validate($_POST)) {
                return RequestResult::buildStatusRequestResult();
            }

            if (isset($sessionService[self::SESS_ALBUM_DATA])) {
                $sessionAlbumData = $sessionService[self::SESS_ALBUM_DATA];
                $sessionAlbumData[self::INSTANCES_FIELD][$validator[self::PLATFORM_FIELD]] = [
                    self::PLATFORM_ID_FIELD => $validator[self::PLATFORM_ID_FIELD],
                    self::TITLE_FIELD => $validator[self::TITLE_FIELD],
                    self::ARTIST_NAME_FIELD => $validator[self::ARTIST_NAME_FIELD],
                    self::COVER_URL_FIELD => $validator[self::COVER_URL_FIELD]
                ];

                if ($validator[self::PLATFORM_FIELD] == PlatformHelperFactory::DEEZER) {
                    $sessionAlbumData[self::COVER_URL_FIELD] = $validator[self::COVER_URL_FIELD];
                }
            } else {
                $sessionAlbumData = [
                    self::TITLE_FIELD => $validator[self::TITLE_FIELD],
                    self::ARTIST_NAME_FIELD => $validator[self::ARTIST_NAME_FIELD],
                    self::COVER_URL_FIELD => $validator[self::COVER_URL_FIELD],
                    self::INSTANCES_FIELD => [
                        $validator[self::PLATFORM_FIELD] => [
                            self::PLATFORM_ID_FIELD => $validator[self::PLATFORM_ID_FIELD],
                            self::TITLE_FIELD => $validator[self::TITLE_FIELD],
                            self::ARTIST_NAME_FIELD => $validator[self::ARTIST_NAME_FIELD],
                            self::COVER_URL_FIELD => $validator[self::COVER_URL_FIELD]
                        ]
                    ]
                ];
            }

            foreach (array_keys(PlatformHelperFactory::PLATFORMS) as $platform) {
                if (!array_key_exists($platform, $sessionAlbumData[self::INSTANCES_FIELD])) {
                    $helper = PlatformHelperFactory::get($platform, $this->serviceProvider);
                    $sessionAlbumData[self::INSTANCES_FIELD][$platform] = $helper->searchExactMatch($sessionAlbumData[self::TITLE_FIELD], $sessionAlbumData[self::ARTIST_NAME_FIELD]);
                }
            }

            $sessionService[self::SESS_ALBUM_DATA] = $sessionAlbumData;
            return RequestResult::buildRedirectRequestResult('/Album/show');
        }
    }

    #[Route, RequiredRequestMethod(RequestMethod::GET)]
    public function searchPlatform(): RequestResult
    {
        $validator = new DataValidator();
        $validator->createField(self::CALLBACK_FIELD, FieldType::HIDDEN, required: false);
        $validator->createField(self::TITLE_PREFIX_FIELD, FieldType::HIDDEN, required: false);
        $validator->createField(self::QUERY_FIELD, FieldType::TEXT, '', extras: ['class' => 'form-control']);
        $f = $validator->createField(self::PLATFORM_FIELD, FieldType::SELECT, PlatformHelperFactory::DEEZER, required: false, extras: ['class' => 'form-select']);
        if ($f instanceof WithOptions) {
            $f->addOptions(PlatformHelperFactory::PLATFORMS);
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
                    $platformHelper = PlatformHelperFactory::get($validator[self::PLATFORM_FIELD], $this->serviceProvider);
                    $searchResults = $platformHelper->search($validator[self::QUERY_FIELD]);

                    if (!empty($searchResults)) {
                        $dbService = $this->serviceProvider->getDatabaseService();
                        $dbConn = $dbService->open();

                        $queryMarks = implode(',', array_pad([], count($searchResults), '?'));
                        $values = [$platformHelper->getPlatform()];
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

        return new RequestResult(data: [
            'validator' => $validator,
            'search_result_validator' => self::createSearchResultValidator($validator[self::PLATFORM_FIELD]),
            'search_results' => $searchResults,
            'title_prefix' => $_REQUEST[self::TITLE_PREFIX_FIELD] ?? null,
            'callback' => $_REQUEST[self::CALLBACK_FIELD] ?? null,
            'previous_searches' => $previousSearches
        ]);
    }

    private static function createSearchResultValidator(?string $platform = null): DataValidator
    {
        $validator = new DataValidator();
        $validator->createField(self::PLATFORM_FIELD, FieldType::HIDDEN, $platform)
            ->addFilter(new In(array_keys(PlatformHelperFactory::PLATFORMS)));
        $validator->createField(self::TITLE_FIELD, FieldType::HIDDEN);
        $validator->createField(self::ARTIST_NAME_FIELD, FieldType::HIDDEN);
        $validator->createField(self::COVER_URL_FIELD, FieldType::HIDDEN, required: false);
        $validator->createField(self::PLATFORM_ID_FIELD, FieldType::HIDDEN);
        return $validator;
    }

    private static function createPreviousSearchValidator(DataValidator $sourceValidator, string $query): DataValidator
    {
        $validator = new DataValidator();
        $validator->createField(self::PLATFORM_FIELD, FieldType::HIDDEN, $sourceValidator[self::PLATFORM_FIELD] ?? PlatformHelperFactory::DEEZER);
        $validator->createField(self::CALLBACK_FIELD, FieldType::HIDDEN, $sourceValidator[self::CALLBACK_FIELD], required: false);
        $validator->createField(self::TITLE_PREFIX_FIELD, FieldType::HIDDEN, $sourceValidator[self::TITLE_PREFIX_FIELD], required: false);
        $validator->createField(self::QUERY_FIELD, FieldType::HIDDEN, $query);
        return $validator;
    }

    #[Route, RequiredRequestMethod(RequestMethod::GET)]
    public function show(array $params): RequestResult
    {
        $sessionService = $this->serviceProvider->getSessionService();

        $albumDetails = null;
        if (!empty($params)) {
            $albumId = $params[0];

            $dbService = $this->serviceProvider->getDatabaseService();
            $dbConn = $dbService->open();

            try {
                $sql = "SELECT `al`.*, `ar`.`name` AS `artist_name`
                            FROM `albums` AS `al`
                            LEFT JOIN `artists` AS `ar`
                                ON `al`.`artist_id` = `ar`.`id`
                            WHERE `al`.`id` = ?";
                if (($albumRow = $dbConn->getRow($sql, \PDO::FETCH_ASSOC, $albumId)) === false) {
                    throw new Exception('An error has occured while querying the database', E_USER_ERROR);
                }

                $sql = "SELECT `platform`, `platform_id` FROM `album_instances` WHERE `album_id` = ?";
                if (($instances = $dbConn->getIndexed($sql, self::PLATFORM_FIELD, \PDO::FETCH_ASSOC, $albumId)) === false) {
                    throw new Exception('An error has occured while querying the database', E_USER_ERROR);
                }

                $albumDetails = $albumRow;
                $albumDetails[self::COVER_URL_FIELD] = sprintf("%s%s/cover_500.jpg", $this->serviceProvider->getConfigService()->getValue('covers.base_url'), $albumRow['slug']);
                $albumDetails[self::INSTANCES_FIELD] = $instances;
            } finally {
            }
        } else if (isset($sessionService[self::SESS_ALBUM_DATA])) {
            $albumDetails = $sessionService[self::SESS_ALBUM_DATA];
        }

        if ($albumDetails === false) {
            return RequestResult::buildStatusRequestResult();
        }

        array_walk($albumDetails[self::INSTANCES_FIELD], function (&$instance, $platform) {
            if (!empty($instance[self::PLATFORM_ID_FIELD])) {
                $helper = PlatformHelperFactory::get($platform, $this->serviceProvider);
                $instance['url'] = $helper->getLookUpURL($instance[self::PLATFORM_ID_FIELD]);
            }
        });

        return new RequestResult(data: [
            'album_details' => $albumDetails,
            'platforms' => PlatformHelperFactory::PLATFORMS,
            'search_query_params' => self::SEARCH_QUERY_PARAMS
        ]);
    }

    #[Route, RequiredRequestMethod(RequestMethod::POST)]
    public function commit(): RequestResult
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

            $sql = "SELECT `id` FROM `artists` WHERE `name` = ?";
            if (($artistId = $dbConn->getValue($sql, $sessionAlbumData[self::ARTIST_NAME_FIELD])) === false) {
                $sql = "INSERT INTO `artists` (`name`) VALUE (?)";
                if ($dbConn->exec($sql, $sessionAlbumData[self::ARTIST_NAME_FIELD]) === false) {
                    throw new Exception('A database error has occured');
                }
                $artistId = $dbConn->lastInsertId();
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
                $filePath = "{$outputPath}/cover_{$size}.jpg";
                if (file_put_contents($filePath, $coverImage) === false) {
                    throw new Exception('Unable to write cover file');
                }
            }

            $dbConn->commit();

            unset($sessionService[self::SESS_ALBUM_DATA]);

            trigger_notif("The album has been successfully added with the slug '{$slug}'");
            return RequestResult::buildRedirectRequestResult("/Album/show/{$albumId}");
        } catch (Exception $e) {
            $dbConn->rollBack();
            trigger_error($e->getMessage());
            return RequestResult::buildRedirectRequestResult("/Album/show");
        }
    }
}
