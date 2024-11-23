<?php

declare(strict_types=1);

use chsxf\MFX\Attributes\AnonymousRoute;
use chsxf\MFX\Attributes\Route;
use chsxf\MFX\RequestResult;
use chsxf\MFX\Routers\BaseRouteProvider;

class Home extends BaseRouteProvider
{
    #[Route, AnonymousRoute]
    public function show(): RequestResult
    {
        $dbService = $this->serviceProvider->getDatabaseService();
        $dbConn = $dbService->open();

        $sql = "SELECT `slug`, `name` FROM `albums` ORDER BY `created_at` DESC LIMIT 10";
        $albums = $dbConn->getPairs($sql);

        return new RequestResult(null, [
            'albums' => $albums
        ]);
    }
}
