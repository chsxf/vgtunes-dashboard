<?php

declare(strict_types=1);

use chsxf\MFX\Attributes\AnonymousRoute;
use chsxf\MFX\Attributes\RequiredRequestMethod;
use chsxf\MFX\Attributes\Route;
use chsxf\MFX\HttpStatusCodes;
use chsxf\MFX\RequestMethod;
use chsxf\MFX\RequestResult;
use chsxf\MFX\Routers\BaseRouteProvider;

final class JsonGenerator extends BaseRouteProvider
{
    #[Route, AnonymousRoute, RequiredRequestMethod(RequestMethod::GET)]
    public function generate(): RequestResult
    {
        if (!$this->serviceProvider->getAuthenticationService()->hasAuthenticatedUser()) {
            $validToken = $this->serviceProvider->getConfigService()->getValue('generator.key');
            $receivedToken = trim($_SERVER['HTTP_VGTUNES_TOKEN'] ?? '');
            if (empty($receivedToken) || base64_decode($receivedToken) != $validToken) {
                return RequestResult::buildJSONRequestResult([], statusCode: HttpStatusCodes::unauthorized);
            }
        }

        $dbService = $this->serviceProvider->getDatabaseService();
        $dbConn = $dbService->open();

        $sql = "SELECT * FROM `artists` ORDER BY `name` ASC";
        $artists = $dbConn->getIndexed($sql, 'id', \PDO::FETCH_ASSOC);

        $sql = "SELECT * FROM `albums` ORDER BY `title` ASC";
        $albums = $dbConn->getIndexed($sql, 'id', \PDO::FETCH_ASSOC);
        $albums = array_map(function ($album) use ($artists) {
            $album['artists'] = [$artists[$album['artist_id']]['slug']];
            $album['instances'] = [];
            unset($album['id'], $album['artist_id']);
            return $album;
        }, $albums);

        $sql = "SELECT * FROM `album_instances`";
        $instances = $dbConn->get($sql, \PDO::FETCH_ASSOC);

        foreach ($instances as $instance) {
            $albums[$instance['album_id']]['instances'][$instance['platform']] = $instance['platform_id'];
        }

        $remappedArtists = [];
        foreach ($artists as $artist) {
            $remappedArtists[$artist['slug']] = $artist['name'];
        }

        if (empty($_GET['raw'])) {
            $this->serviceProvider->getRequestService()->setAttachmentHeaders('dashboard-export.json', 'application/json');
        }

        return RequestResult::buildJSONRequestResult([
            'albums' => array_values($albums),
            'artists' => $remappedArtists
        ]);
    }
}
