<?php

use chsxf\MFX\Attributes\RequiredRequestMethod;
use chsxf\MFX\Attributes\Route;
use chsxf\MFX\DataValidator;
use chsxf\MFX\DataValidator\FieldType;
use chsxf\MFX\DataValidator\Filters\ExistsInDB;
use chsxf\MFX\HttpStatusCodes;
use chsxf\MFX\RequestMethod;
use chsxf\MFX\RequestResult;
use chsxf\MFX\Routers\BaseRouteProvider;

final class FeaturedAlbums extends BaseRouteProvider
{
    private const string ALBUM_ID_FIELD = '0';

    #[Route]
    public function show(): RequestResult
    {
        $dbConn = $this->serviceProvider->getDatabaseService()->open();

        $sql = "SELECT `fa`.`id`, `al`.`title`, `al`.`slug`, `ar`.`name`
                    FROM `featured_albums` AS `fa`
                    LEFT JOIN `albums` AS `al`
                        ON `fa`.`album_id` = `al`.`id`
                    LEFT JOIN `artists` AS `ar`
                        ON `al`.`artist_id` = `ar`.`id`
                    ORDER BY `fa`.`featured_at` DESC";
        if (($featuredAlbums = $dbConn->get($sql, \PDO::FETCH_ASSOC)) === false) {
            trigger_error('An error has occured while recovering featured albums');
            return RequestResult::buildStatusRequestResult(HttpStatusCodes::internalServerError);
        }

        return new RequestResult(data: [
            'featured_albums' => $featuredAlbums
        ]);
    }

    #[Route, RequiredRequestMethod(RequestMethod::POST)]
    public function feature(array $params): RequestResult
    {
        $dbConn = $this->serviceProvider->getDatabaseService()->open();

        $validator = new DataValidator();
        $validator->createField(self::ALBUM_ID_FIELD, FieldType::TEXT)
            ->addFilter(new ExistsInDB('albums', 'id', $dbConn));

        if (!$validator->validate($params)) {
            return RequestResult::buildStatusRequestResult(HttpStatusCodes::badRequest);
        }

        $sql = "INSERT INTO `featured_albums` (`album_id`) VALUE (?)";
        if ($dbConn->exec($sql, $validator[self::ALBUM_ID_FIELD]) === false) {
            trigger_error('An error has occured while inserting the new featured album');
        } else {
            trigger_notif('Album featured successfully');
        }
        return RequestResult::buildRedirectRequestResult("/Album/show/{$validator[self::ALBUM_ID_FIELD]}");
    }
}
