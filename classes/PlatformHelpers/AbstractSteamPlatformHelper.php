<?php

namespace PlatformHelpers;

use chsxf\MFX\Services\IDatabaseService;
use PlatformAlbum;

abstract class AbstractSteamPlatformHelper implements IPlatformHelper
{
    private const string LOOKUP_URL = 'https://store.steampowered.com/app/{PLATFORM_ID}';
    private const string CAPSULE_URL = 'https://shared.cloudflare.steamstatic.com/store_item_assets/steam/apps/{PLATFORM_ID}/header.jpg?t={NOW}';

    abstract protected function sqlTypeClause(): string;

    public function __construct(private readonly IDatabaseService $databaseService) {}

    public function getLookUpURL(string $platformId): string
    {
        return str_replace('{PLATFORM_ID}', $platformId, self::LOOKUP_URL);
    }

    public function search(string $query): array
    {
        $dbConn = $this->databaseService->open();

        $sql = "SELECT `app_id`, `name`
                    FROM `steam_products`
                    WHERE `name` LIKE ? AND `type` {$this->sqlTypeClause()}
                    LIMIT 50";
        $values = ["%{$query}%"];

        if (($dbResults = $dbConn->get($sql, \PDO::FETCH_ASSOC, $values)) === false) {
            throw new PlatformHelperException("A database error has occured");
        }

        $results = [];
        foreach ($dbResults as $dbResult) {
            $id = $dbResult['app_id'];
            $imgUrl = str_replace(['{PLATFORM_ID}', '{NOW}'], [$id, time()], self::CAPSULE_URL);
            $results[] = new PlatformAlbum($dbResult['name'], $id, 'n/a', $imgUrl);
        }
        return $results;
    }

    public function searchExactMatch(string $title, string $ignoredArtistName): ?array
    {
        $query = $title;

        foreach (PlatformAlbum::CLEAN_REGEXP as $replacementRegex) {
            if ($replacementRegex !== null) {
                $query = trim(preg_replace($replacementRegex, '', $query));
            }

            $passQueryResults = $this->search($query);
            foreach ($passQueryResults as $result) {
                $resultTitle = PlatformAlbum::cleanupAlbumTitle($result->title);
                if (strcasecmp($resultTitle, $query) == 0) {
                    return iterator_to_array($result);
                }
            }
        }

        return null;
    }
}
