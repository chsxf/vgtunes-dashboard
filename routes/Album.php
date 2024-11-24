<?php

declare(strict_types=1);

use chsxf\MFX\Attributes\AnonymousRoute;
use chsxf\MFX\Attributes\RequiredRequestMethod;
use chsxf\MFX\Attributes\Route;
use chsxf\MFX\DataValidator;
use chsxf\MFX\DataValidator\FieldType;
use chsxf\MFX\DataValidator\Filters\RegExp;
use chsxf\MFX\RequestMethod;
use chsxf\MFX\RequestResult;
use chsxf\MFX\Routers\BaseRouteProvider;
use chsxf\MFX\StringTools;

final class Album extends BaseRouteProvider
{
    private const PLATFORM_ID_SUFFIX = '_platform_id';
    private const PLATFORM_ID_MAX_LENGTH = 32;
    private const NAMES_MAX_LENGTH = 256;

    private const NAME = 'name';
    private const ARTIST_NAME = 'artist_name';
    private const APPLE_MUSIC = 'apple_music';
    private const APPLE_MUSIC_PLATFORM_ID = self::APPLE_MUSIC . self::PLATFORM_ID_SUFFIX;
    private const DEEZER = 'deezer';
    private const DEEZER_PLATFORM_ID = self::DEEZER . self::PLATFORM_ID_SUFFIX;
    private const SPOTIFY = 'spotify';
    private const SPOTIFY_PLATFORM_ID = self::SPOTIFY . self::PLATFORM_ID_SUFFIX;

    #[Route, AnonymousRoute, RequiredRequestMethod(RequestMethod::GET), RequiredRequestMethod(RequestMethod::POST)]
    public function add(): RequestResult
    {
        $this->serviceProvider->getScriptService()->add('/js/prefill.js');

        $namesExtraArguments = ['size' => strval(self::NAMES_MAX_LENGTH), 'maxlength' => strval(self::NAMES_MAX_LENGTH)];

        $validator = new DataValidator();
        $validator->createField(self::NAME, FieldType::TEXT, extras: $namesExtraArguments)
            ->addFilter(RegExp::stringLength(max: self::NAMES_MAX_LENGTH));
        $validator->createField(self::ARTIST_NAME, FieldType::TEXT, extras: $namesExtraArguments)
            ->addFilter(RegExp::stringLength(max: self::NAMES_MAX_LENGTH));

        $platformIdExtraArguments = ['size' => strval(self::PLATFORM_ID_MAX_LENGTH), 'maxlength' => strval(self::PLATFORM_ID_MAX_LENGTH)];

        $validator->createField(self::APPLE_MUSIC_PLATFORM_ID, FieldType::TEXT, required: false, extras: $platformIdExtraArguments)
            ->addFilter(RegExp::stringLength(max: self::PLATFORM_ID_MAX_LENGTH));
        $validator->createField(self::DEEZER_PLATFORM_ID, FieldType::TEXT, required: false, extras: $platformIdExtraArguments)
            ->addFilter(RegExp::stringLength(max: self::PLATFORM_ID_MAX_LENGTH));
        $validator->createField(self::SPOTIFY_PLATFORM_ID, FieldType::TEXT, required: false, extras: $platformIdExtraArguments)
            ->addFilter(RegExp::stringLength(max: self::PLATFORM_ID_MAX_LENGTH));

        $platformPairs = [
            self::APPLE_MUSIC => self::APPLE_MUSIC_PLATFORM_ID,
            self::DEEZER => self::DEEZER_PLATFORM_ID,
            self::SPOTIFY => self::SPOTIFY_PLATFORM_ID
        ];

        if ($this->serviceProvider->getRequestService()->getRequestMethod() == RequestMethod::POST && $validator->validate($_POST)) {
            $dbService = $this->serviceProvider->getDatabaseService();
            $dbConn = $dbService->open();

            $dbConn->beginTransaction();

            try {
                $hasAtLeastOnePlatformId = false;
                foreach ($platformPairs as $platform => $platformIdFieldName) {
                    if (!empty($validator[$platformIdFieldName])) {
                        $hasAtLeastOnePlatformId = true;
                        break;
                    }
                }
                if (!$hasAtLeastOnePlatformId) {
                    throw new Exception('At least one platform ID must be filled in');
                }

                $sql = "SELECT `id` FROM `artists` WHERE `name` = ?";
                if (($artistId = $dbConn->getValue($sql, $validator[self::ARTIST_NAME])) === false) {
                    $sql = "INSERT INTO `artists` (`name`) VALUE (?)";
                    if ($dbConn->exec($sql, $validator[self::ARTIST_NAME]) === false) {
                        throw new Exception('A database error has occured');
                    }
                    $artistId = $dbConn->lastInsertId();
                }

                $slug = null;
                while ($slug === null) {
                    $candidateSlug = StringTools::generateRandomString(8, StringTools::CHARSET_ALPHANUMERIC_LC);
                    $sql = 'SELECT COUNT(`id`) FROM `albums` WHERE `slug` = ?';
                    $count = $dbConn->getValue($sql, $candidateSlug);
                    if ($count === false || $count === null) {
                        throw new Exception('A database error has occured');
                    }

                    if ($count == 0) {
                        $slug = $candidateSlug;
                    }
                }

                $sql = 'INSERT INTO `albums` (`slug`, `name`, `artist_id`) VALUE (?, ?, ?)';
                if (!$dbConn->exec($sql, $slug, $validator['name'], $artistId)) {
                    throw new Exception('A database error has occured');
                }
                $albumId = $dbConn->lastInsertId();

                foreach ($platformPairs as $platform => $platformIdFieldName) {
                    $platformIdValue = $validator[$platformIdFieldName];
                    if (!empty($platformIdValue)) {
                        $sql = 'INSERT INTO `album_instances` VALUE (?, ?, ?)';
                        if (!$dbConn->exec($sql, $albumId, $platform, $platformIdValue)) {
                            throw new Exception('A database error has occured');
                        }
                    }
                }

                $dbConn->commit();
                trigger_notif("The album has been successfully added with the slug '{$slug}'");
                return RequestResult::buildRedirectRequestResult();
            } catch (Exception $e) {
                $dbConn->rollBack();
                trigger_error($e->getMessage());
            }
        }

        return new RequestResult(null, ['validator' => $validator]);
    }
}
