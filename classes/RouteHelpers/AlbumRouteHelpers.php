<?php

declare(strict_types=1);

namespace RouteHelpers;

use AlbumDataStatus;
use chsxf\MFX\DatabaseConnectionInstance;
use chsxf\MFX\DataValidator;
use chsxf\MFX\DataValidator\Fields\WithOptions;
use chsxf\MFX\DataValidator\FieldType;
use chsxf\MFX\DataValidator\Filters\In;
use chsxf\MFX\Services\IConfigService;
use Exception;
use Platform;
use PlatformAvailability;

trait AlbumRouteHelpers
{
    public const string SESS_ALBUM_DATA = 'album-data';
    public const string SESS_BULK_SELECTION = 'bulk-selection';

    private const string FUNCTION = 'function';
    private const string DATA_STATUS = 'data_status';
    private const string NEW_DATA = 'new_data';
    private const string ALBUM_ID = 'album_id';

    private const string QUERY_FIELD = 'q';
    private const string PLATFORM_FIELD = 'platform';
    private const string CALLBACK_FIELD = 'callback';
    private const string TITLE_PREFIX_FIELD = 'title_prefix';
    private const string TITLE_FIELD = 'title';
    private const string ARTISTS_FIELD = 'artists';
    private const string COVER_URL_FIELD = 'cover_url';
    private const string INSTANCES_FIELD = 'instances';
    private const string PLATFORM_ID_FIELD = 'platform_id';
    private const string BACK_URL_FIELD = 'back_url';
    private const string START_AT_FIELD = 'start_at';
    private const string AVAILABILITY_FIELD = 'availability';
    private const string BULK_SELECTION_FIELD = 'bulk_selection';

    private const string ALLOW_BULK_SELECTION = 'allow_bulk_selection';
    private const string CONTINUE_WITH_BULK_SELECTION = 'continue_with_bulk_selection';
    private const string PREVIOUS_SEARCHES = 'previous_searches';

    private static function createInstanceEntry(DataValidator $validator, array $artists, AlbumDataStatus $status = AlbumDataStatus::new, PlatformAvailability $availability = PlatformAvailability::Available, array $overrides = [])
    {
        return [
            self::PLATFORM_ID_FIELD => $overrides[self::PLATFORM_ID_FIELD] ?? $validator[self::PLATFORM_ID_FIELD],
            self::TITLE_FIELD => $overrides[self::TITLE_FIELD] ?? $validator[self::TITLE_FIELD],
            self::ARTISTS_FIELD => $artists,
            self::COVER_URL_FIELD => $overrides[self::COVER_URL_FIELD] ?? $validator[self::COVER_URL_FIELD],
            self::DATA_STATUS => AlbumDataStatus::new,
            self::AVAILABILITY_FIELD => PlatformAvailability::Available
        ];
    }

    private static function createSearchPageValidator(?DataValidator $sourceValidator, int $startAt): DataValidator
    {
        $validator = new DataValidator();
        $validator->createField(self::CALLBACK_FIELD, FieldType::TEXT, $sourceValidator[self::CALLBACK_FIELD] ?? null, required: false);
        $validator->createField(self::BACK_URL_FIELD, FieldType::TEXT, $sourceValidator[self::BACK_URL_FIELD] ?? null, required: false);
        $validator->createField(self::START_AT_FIELD, FieldType::POSITIVEZERO_INTEGER, $startAt, required: false);
        $validator->createField(self::TITLE_PREFIX_FIELD, FieldType::TEXT, $sourceValidator[self::TITLE_PREFIX_FIELD] ?? null, required: false);
        $validator->createField(self::QUERY_FIELD, FieldType::TEXT, $sourceValidator[self::QUERY_FIELD] ?? '', extras: ['class' => 'form-control']);
        $validator->createField(self::ALLOW_BULK_SELECTION, FieldType::TEXT, $sourceValidator[self::ALLOW_BULK_SELECTION] ?? null, required: false);
        $f = $validator->createField(self::PLATFORM_FIELD, FieldType::SELECT, $sourceValidator[self::PLATFORM_FIELD] ?? Platform::deezer->value, required: false, extras: ['class' => 'form-select']);
        if ($f instanceof WithOptions) {
            $f->addOptions(Platform::PLATFORMS);
        }
        return $validator;
    }

    private static function createSearchResultValidator(?string $platform = null): DataValidator
    {
        $validator = new DataValidator();
        $validator->createField(self::PLATFORM_FIELD, FieldType::TEXT, $platform)
            ->addFilter(new In(array_keys(Platform::PLATFORMS)));
        $validator->createField(self::TITLE_FIELD, FieldType::TEXT);
        $validator->createField(self::ARTISTS_FIELD, FieldType::TEXT);
        $validator->createField(self::COVER_URL_FIELD, FieldType::TEXT, required: false);
        $validator->createField(self::PLATFORM_ID_FIELD, FieldType::TEXT);
        return $validator;
    }

    private static function createPreviousSearchValidator(DataValidator $sourceValidator, string $query): DataValidator
    {
        $validator = new DataValidator();
        $validator->createField(self::PLATFORM_FIELD, FieldType::TEXT, $sourceValidator[self::PLATFORM_FIELD] ?? Platform::deezer->value);
        $validator->createField(self::CALLBACK_FIELD, FieldType::TEXT, $sourceValidator[self::CALLBACK_FIELD], required: false);
        $validator->createField(self::BACK_URL_FIELD, FieldType::TEXT, $sourceValidator[self::BACK_URL_FIELD], required: false);
        $validator->createField(self::TITLE_PREFIX_FIELD, FieldType::TEXT, $sourceValidator[self::TITLE_PREFIX_FIELD], required: false);
        $validator->createField(self::ALLOW_BULK_SELECTION, FieldType::TEXT, $sourceValidator[self::ALLOW_BULK_SELECTION], required: false);
        $validator->createField(self::QUERY_FIELD, FieldType::TEXT, $query);
        return $validator;
    }

    private static function createBulkSelectionValidator(): DataValidator
    {
        $validator = new DataValidator();
        $validator->createField(self::BULK_SELECTION_FIELD, FieldType::TEXT, required: true);
        return $validator;
    }

    private static function getSavedAlbumDetails(DatabaseConnectionInstance $dbConn, IConfigService $configService, int $albumId): array
    {
        $sql = "SELECT * FROM `albums` WHERE `id` = ?";
        if (($albumRow = $dbConn->getRow($sql, \PDO::FETCH_ASSOC, $albumId)) === false) {
            throw new Exception('An error has occured while querying album details', E_USER_ERROR);
        }

        $sql = "SELECT `platform`, `platform_id`, `availability` FROM `album_instances` WHERE `album_id` = ?";
        if (($instances = $dbConn->getIndexed($sql, self::PLATFORM_FIELD, \PDO::FETCH_ASSOC, $albumId)) === false) {
            throw new Exception("An error has occured while querying album's instances", E_USER_ERROR);
        }

        $sql = "SELECT `ar`.`name`
                    FROM `album_artists` AS `aa`
                    LEFT JOIN `artists` AS `ar`
                        ON `aa`.`artist_id` = `ar`.`id`
                    WHERE `aa`.`album_id` = ?";
        if (($artists = $dbConn->getColumn($sql, $albumId)) === false) {
            throw new Exception("An error has occured while querying album's artists", E_USER_ERROR);
        }

        $albumDetails = $albumRow;
        $albumDetails[self::COVER_URL_FIELD] = sprintf("%s%s/cover_500.webp", $configService->getValue('covers.base_url'), $albumRow['slug']);
        $albumDetails[self::ARTISTS_FIELD] = $artists;
        $albumDetails[self::INSTANCES_FIELD] = array_map(fn($instance) => array_merge($instance, [self::DATA_STATUS => AlbumDataStatus::saved]), $instances);
        return $albumDetails;
    }

    private static function createEditSearchQueryParams(int $albumId): array
    {
        return [
            self::CALLBACK_FIELD => "/Album/edit/{$albumId}",
            self::TITLE_PREFIX_FIELD => 'Edit Album',
            self::BACK_URL_FIELD => "/Album/show/{$albumId}"
        ];
    }
}
