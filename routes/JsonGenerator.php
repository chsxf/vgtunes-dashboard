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
            $album['artists'] = [];
            $album['instances'] = [];
            unset($album['id']);
            return $album;
        }, $albums);

        $sql = "SELECT * FROM `album_instances`";
        $instances = $dbConn->get($sql, \PDO::FETCH_ASSOC);
        foreach ($instances as $instance) {
            $albums[$instance['album_id']]['instances'][$instance['platform']] = $instance['platform_id'];
        }

        $sql = "SELECT * FROM `album_artists`";
        $albumArtists = $dbConn->get($sql, \PDO::FETCH_ASSOC);
        foreach ($albumArtists as $albumArtist) {
            $albums[$albumArtist['album_id']]['artists'][] = $artists[$albumArtist['artist_id']]['name'];
        }

        $remappedArtists = [];
        foreach ($artists as $artist) {
            $remappedArtists[$artist['slug']] = $artist['name'];
        }

        $sql = "SELECT `al`.`slug`, `fa`.`featured_at`
                    FROM `featured_albums` AS `fa`
                    LEFT JOIN `albums` AS `al`
                        ON `al`.`id` = `fa`.`album_id`
                    ORDER BY `featured_at` DESC";
        $featuredAlbums = $dbConn->get($sql, \PDO::FETCH_ASSOC);

        if (empty($_GET['raw'])) {
            $this->serviceProvider->getRequestService()->setAttachmentHeaders('dashboard-export.json', 'application/json');
        }

        $result = [
            'albums' => array_values($albums),
            'artists' => $remappedArtists,
            'featured_albums' => $featuredAlbums
        ];
        return RequestResult::buildJSONRequestResult(json_encode($result), true);
    }
}
