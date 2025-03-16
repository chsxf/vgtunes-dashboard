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

final class Artists extends BaseRouteProvider implements IPaginationProvider
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

        $sql = "SELECT COUNT(`id`) FROM `artists`";
        $values = [];
        if ($this->filteredQuery !== null) {
            $sql .= " WHERE `name` LIKE ?";
            $values[] = "%{$this->filteredQuery}%";
        }

        $dbService = $this->serviceProvider->getDatabaseService();
        $dbConn = $dbService->open();
        if (($queriedCount = $dbConn->getValue($sql, $values)) !== false) {
            $this->totalItemCountBuffer = $queriedCount;
        } else {
            trigger_error('An error has occured while enumerating artists');
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
            $artists = self::search($this->serviceProvider, $dbConn, $pageManager->getCurrentPageStart(), $pageManager->getItemCountPerPage(), $this->filteredQuery);
        } catch (Exception $e) {
            trigger_error($e->getMessage(), E_ERROR);
            return RequestResult::buildRedirectRequestResult('/');
        }

        return new RequestResult(data: [
            'artists' => $artists,
            'pm' => $pageManager,
            'frontend_base_url' => $this->serviceProvider->getConfigService()->getValue('frontend.base_url')
        ]);
    }

    public static function search(ICoreServiceProvider $coreServiceProvider, DatabaseConnectionInstance $dbConn, int $start, int $count, ?string $query = null, ?string $orderClause = null): array
    {
        $sql = "SELECT * FROM `artists`";
        $values = [];
        if ($query !== null) {
            $sql .= " WHERE `name` LIKE ?";
            $values[] = "%{$query}%";
        }
        if ($orderClause !== null) {
            $sql .= " {$orderClause}";
        } else {
            $sql .= " ORDER BY `name` ASC";
        }
        $sql .= sprintf(" LIMIT %d, %d", $start, $count);
        if (($artists = $dbConn->get($sql, \PDO::FETCH_ASSOC, $values)) === false) {
            throw new Exception('An error has occured while loading artists.');
        }
        return $artists;
    }
}
