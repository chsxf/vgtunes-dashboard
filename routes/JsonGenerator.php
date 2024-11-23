<?php

declare(strict_types=1);

use chsxf\MFX\Attributes\AnonymousRoute;
use chsxf\MFX\Attributes\Route;
use chsxf\MFX\RequestResult;
use chsxf\MFX\Routers\BaseRouteProvider;

final class JsonGenerator extends BaseRouteProvider
{
    #[Route, AnonymousRoute]
    public function generate(): RequestResult
    {
        $dbService = $this->serviceProvider->getDatabaseService();
        $dbConn = $dbService->open();

        $sql = "SELECT `id`, `slug`, `name` FROM `albums` ORDER BY `name`";
        $albums = $dbConn->get($sql, \PDO::FETCH_ASSOC);

        $sql = "SELECT * FROM `album_instances`";
        $instances = $dbConn->get($sql, \PDO::FETCH_ASSOC);

        $result = [];
        foreach ($albums as $album) {
            $result[$album['id']] = array_merge($album, ['instances' => []]);
        }

        foreach ($instances as $instance) {
            $result[$instance['album_id']]['instances'][$instance['platform']] = $instance['platform_id'];
        }

        $result = array_values($result);
        $result = array_map(function ($item) {
            unset($item['id']);
            return $item;
        }, $result);
        return RequestResult::buildJSONRequestResult($result);
    }
}
