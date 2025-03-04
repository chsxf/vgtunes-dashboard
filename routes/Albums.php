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
    private int $totalItemCountBuffer;

    private readonly ?string $filteredQuery;

    public function __construct(protected readonly ICoreServiceProvider $serviceProvider)
    {
        $query = trim($_REQUEST['q'] ?? '');
        if (empty($query)) {
            $this->filteredQuery = null;
        } else {
            $this->filteredQuery = $query;
        }

        $sql = "SELECT COUNT(`id`) FROM `albums`";
        $values = [];
        if ($this->filteredQuery !== null) {
            $sql .= " WHERE `title` LIKE ?";
            $values[] = "%{$this->filteredQuery}%";
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
        $pageManager = new PaginationManager($this, ['q']);

        $dbService = $this->serviceProvider->getDatabaseService();
        $dbConn = $dbService->open();

        try {
            $albums = self::search($this->serviceProvider, $dbConn, $pageManager->getCurrentPageStart(), $pageManager->getItemCountPerPage(), $this->filteredQuery);
        } catch (Exception $e) {
            trigger_error($e->getMessage(), E_ERROR);
            return RequestResult::buildRedirectRequestResult('/');
        }

        return new RequestResult(data: ['albums' => $albums, 'pm' => $pageManager, 'frontend_base_url' => $this->serviceProvider->getConfigService()->getValue('frontend.base_url')]);
    }

    public static function search(ICoreServiceProvider $coreServiceProvider, DatabaseConnectionInstance $dbConn, int $start, int $count, ?string $query = null, ?string $orderClause = null): array
    {
        $sql = "SELECT `al`.`id`, `al`.`slug`, `al`.`title`, `ar`.`name` AS `artist_name`
                    FROM `albums` AS `al`
                    LEFT JOIN `artists` AS `ar`
                        ON `ar`.`id` = `al`.`artist_id`";
        $values = [];
        if ($query !== null) {
            $sql .= " WHERE `al`.`title` LIKE ?";
            $values[] = "%{$query}%";
        }
        if ($orderClause !== null) {
            $sql .= " {$orderClause}";
        } else {
            $sql .= " ORDER BY `al`.`title` ASC";
        }
        $sql .= sprintf(" LIMIT %d, %d", $start, $count);
        if (($albums = $dbConn->get($sql, \PDO::FETCH_ASSOC, $values)) === false) {
            throw new Exception('An error has occured while loading albums.');
        }

        $platformsByAlbum = [];
        if (!empty($albums)) {
            $albumIds = array_map(fn($album) => $album['id'], $albums);
            $marks = implode(',', array_pad([], count($albumIds), '?'));
            $sql = "SELECT `album_id`, `platform`
                        FROM `album_instances`
                        WHERE `album_id` IN ({$marks})";
            if (($albumPlatforms = $dbConn->get($sql, \PDO::FETCH_ASSOC, $albumIds)) === false) {
                throw new Exception('An error has occured while loading album platforms.');
            }

            foreach ($albumPlatforms as $albumPlatform) {
                $id = $albumPlatform['album_id'];
                $platform = $albumPlatform['platform'];
                if (array_key_exists($id, $platformsByAlbum)) {
                    $platformsByAlbum[$id][] = $platform;
                } else {
                    $platformsByAlbum[$id] = [$platform];
                }
            }
        }

        $coversBaseUrl = $coreServiceProvider->getConfigService()->getValue('covers.base_url');
        array_walk($albums, function (&$album, $index) use ($coversBaseUrl, $platformsByAlbum) {
            $album['cover_url'] = sprintf("%s%s/cover_100.webp", $coversBaseUrl, $album['slug']);

            if (array_key_exists($album['id'], $platformsByAlbum)) {
                $album['platforms'] = $platformsByAlbum[$album['id']];
                sort($album['platforms']);
            } else {
                $album['platforms'] = [];
            }
        });

        return $albums;
    }
}
