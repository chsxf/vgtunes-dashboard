<?php

use chsxf\MFX\Attributes\RequiredRequestMethod;
use chsxf\MFX\Attributes\Route;
use chsxf\MFX\DatabaseConnectionInstance;
use chsxf\MFX\IPaginationProvider;
use chsxf\MFX\PaginationManager;
use chsxf\MFX\RequestMethod;
use chsxf\MFX\RequestResult;
use chsxf\MFX\Routers\BaseRouteProvider;
use chsxf\MFX\Services\ICoreServiceProvider;

final class Albums extends BaseRouteProvider implements IPaginationProvider
{
    private const string QUERY_PARAM = 'q';
    private const string UNAVAILABLE_ONLY_PARAM = 'unavailable_only';

    private int $totalItemCountBuffer;

    private readonly ?string $filteredQuery;
    private readonly bool $limitToAlbumsWithUnavailableEntries;

    public function __construct(protected readonly ICoreServiceProvider $serviceProvider)
    {
        $query = trim($_REQUEST[self::QUERY_PARAM] ?? '');
        if (empty($query)) {
            $this->filteredQuery = null;
        } else {
            $this->filteredQuery = $query;
        }

        $this->limitToAlbumsWithUnavailableEntries = !empty($_REQUEST[self::UNAVAILABLE_ONLY_PARAM]);

        $sql = "SELECT COUNT(`id`) FROM `albums`";
        $queryClauses = [];
        $values = [];

        if ($this->limitToAlbumsWithUnavailableEntries) {
            $queryClauses[] = "`id` IN (SELECT DISTINCT `album_id` FROM `album_instances` WHERE `availability` = 'not_available')";
        }

        if ($this->filteredQuery !== null) {
            $queryClauses[] = "`title` LIKE ?";
            $values[] = "%{$this->filteredQuery}%";
        }

        if (!empty($queryClauses)) {
            $sql .= ' WHERE ' . implode(' AND ', $queryClauses);
        }

        $dbService = $this->serviceProvider->getDatabaseService();
        $dbConn = $dbService->open();
        if (($queriedCount = $dbConn->getValue($sql, $values)) !== false) {
            $this->totalItemCountBuffer = $queriedCount;
        } else {
            trigger_error('An error has occured while enumerating albums');
        }
        $dbService->close($dbConn);
    }

    public function totalItemCount(): int
    {
        return $this->totalItemCountBuffer;
    }

    public function defaultPageCount(): int
    {
        return 25;
    }

    #[Route, RequiredRequestMethod(RequestMethod::GET)]
    public function list(): RequestResult
    {
        $pageManager = new PaginationManager($this, [self::QUERY_PARAM, self::UNAVAILABLE_ONLY_PARAM]);

        $dbService = $this->serviceProvider->getDatabaseService();
        $dbConn = $dbService->open();

        $additionalWhereClauses = [];
        if ($this->limitToAlbumsWithUnavailableEntries) {
            $additionalWhereClauses[] = "`id` IN (SELECT DISTINCT `album_id` FROM `album_instances` WHERE `availability` = 'not_available')";
        }

        try {
            $albums = self::search($this->serviceProvider, $dbConn, $pageManager->getCurrentPageStart(), $pageManager->getItemCountPerPage(), $this->filteredQuery, $additionalWhereClauses);
        } catch (Exception $e) {
            trigger_error($e->getMessage(), E_ERROR);
            return RequestResult::buildRedirectRequestResult('/');
        }

        return new RequestResult(data: [
            'albums' => $albums,
            'pm' => $pageManager,
            'frontend_base_url' => $this->serviceProvider->getConfigService()->getValue('frontend.base_url'),
            'unavailable_only' => $this->limitToAlbumsWithUnavailableEntries,
            'query' => $this->filteredQuery
        ]);
    }

    public static function search(ICoreServiceProvider $coreServiceProvider, DatabaseConnectionInstance $dbConn, int $start, int $count, ?string $query = null, array $additionalWhereClauses = [], ?string $orderClause = null): array
    {
        $sql = "SELECT `id`, `slug`, `title`
                    FROM `albums`";
        $values = [];

        if ($query !== null) {
            $additionalWhereClauses[] = "`title` LIKE ?";
            $values[] = "%{$query}%";
        }

        if (!empty($additionalWhereClauses)) {
            $sql .= ' WHERE ' . implode(' AND ', $additionalWhereClauses);
        }

        if ($orderClause !== null) {
            $sql .= " {$orderClause}";
        } else {
            $sql .= " ORDER BY `title` ASC";
        }

        $sql .= sprintf(" LIMIT %d, %d", $start, $count);
        if (($albums = $dbConn->get($sql, \PDO::FETCH_ASSOC, $values)) === false) {
            throw new Exception('An error has occured while loading albums.');
        }

        $platformsByAlbum = [];
        $artistsByAlbum = [];
        if (!empty($albums)) {
            $albumIds = array_map(fn($album) => $album['id'], $albums);
            $marks = implode(',', array_pad([], count($albumIds), '?'));

            $sql = "SELECT `album_id`, `platform`
                        FROM `album_instances`
                        WHERE `album_id` IN ({$marks})";
            if (($albumPlatforms = $dbConn->get($sql, \PDO::FETCH_ASSOC, $albumIds)) === false) {
                throw new Exception('An error has occured while loading album platforms.');
            }
            foreach ($albumPlatforms as $albumArtist) {
                $id = $albumArtist['album_id'];
                $artistName = $albumArtist['platform'];
                if (array_key_exists($id, $platformsByAlbum)) {
                    $platformsByAlbum[$id][] = $artistName;
                } else {
                    $platformsByAlbum[$id] = [$artistName];
                }
            }

            $sql = "SELECT `aa`.`album_id`, `ar`.`name`
                        FROM `album_artists` AS `aa`
                        LEFT JOIN `artists` AS `ar`
                            ON `aa`.`artist_id` = `ar`.`id`
                        WHERE `aa`.`album_id` IN ({$marks})";
            if (($albumArtists = $dbConn->get($sql, \PDO::FETCH_ASSOC, $albumIds)) === false) {
                throw new Exception('An error has occured while loading album artists.');
            }
            foreach ($albumArtists as $albumArtist) {
                $id = $albumArtist['album_id'];
                $artistName = $albumArtist['name'];
                if (array_key_exists($id, $artistsByAlbum)) {
                    $artistsByAlbum[$id][] = $artistName;
                } else {
                    $artistsByAlbum[$id] = [$artistName];
                }
            }
        }

        $coversBaseUrl = $coreServiceProvider->getConfigService()->getValue('covers.base_url');
        array_walk($albums, function (&$album, $index) use ($coversBaseUrl, $platformsByAlbum, $artistsByAlbum) {
            $albumId = $album['id'];
            $album['cover_url'] = sprintf("%s%s/cover_100.webp", $coversBaseUrl, $album['slug']);

            if (array_key_exists($albumId, $platformsByAlbum)) {
                $album['platforms'] = $platformsByAlbum[$albumId];
                sort($album['platforms']);
            } else {
                $album['platforms'] = [];
            }

            if (array_key_exists($albumId, $artistsByAlbum)) {
                $album['artists'] = $artistsByAlbum[$albumId];
                sort($album['artists']);
            } else {
                $album['artists'] = [];
            }
        });

        return $albums;
    }
}
