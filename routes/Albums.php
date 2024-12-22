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

    public function __construct(protected readonly ICoreServiceProvider $serviceProvider)
    {
        $dbService = $this->serviceProvider->getDatabaseService();
        $dbConn = $dbService->open();
        $sql = "SELECT COUNT(`id`) FROM `albums`";
        $this->totalItemCountBuffer = $dbConn->getValue($sql);
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
        $pageManager = new PaginationManager($this);

        $dbService = $this->serviceProvider->getDatabaseService();
        $dbConn = $dbService->open();

        $sql = "SELECT `al`.`id`, `al`.`title`, `ar`.`name` AS `artist_name`
                    FROM `albums` AS `al`
                    LEFT JOIN `artists` AS `ar`
                        ON `ar`.`id` = `al`.`artist_id`
                    ORDER BY `al`.`title` ASC";
        $sql .= $pageManager->sqlLimit();
        if (($albums = $dbConn->get($sql, \PDO::FETCH_ASSOC)) === false) {
            trigger_error('An error has occured while loading albums.', E_ERROR);
            return RequestResult::buildRedirectRequestResult('/');
        }

        return new RequestResult(data: ['albums' => $albums, 'pm' => $pageManager]);
    }
}
