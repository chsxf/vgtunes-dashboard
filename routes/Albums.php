<?php

use chsxf\MFX\Attributes\RequiredRequestMethod;
use chsxf\MFX\Attributes\Route;
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

        $sql = "SELECT `al`.`id`, `al`.`title`, `ar`.`name` AS `artist_name`
                    FROM `albums` AS `al`
                    LEFT JOIN `artists` AS `ar`
                        ON `ar`.`id` = `al`.`artist_id`";
        $values = [];
        if ($this->filteredQuery !== null) {
            $sql .= " WHERE `al`.`title` LIKE ?";
            $values[] = "%{$this->filteredQuery}%";
        }
        $sql .= " ORDER BY `al`.`title` ASC";
        $sql .= $pageManager->sqlLimit();
        if (($albums = $dbConn->get($sql, \PDO::FETCH_ASSOC, $values)) === false) {
            trigger_error('An error has occured while loading albums.', E_ERROR);
            return RequestResult::buildRedirectRequestResult('/');
        }

        return new RequestResult(data: ['albums' => $albums, 'pm' => $pageManager]);
    }
}
