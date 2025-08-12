<?php

namespace PlatformHelpers;

use chsxf\MFX\Services\IDatabaseService;
use PlatformAlbum;

abstract class AbstractSteamPlatformHelper extends AbstractPlatformHelper
{
    use DistanceResultSorterTrait;

    private const string LOOKUP_URL = 'https://store.steampowered.com/app/{PLATFORM_ID}';
    private const string CAPSULE_URL = 'https://shared.cloudflare.steamstatic.com/store_item_assets/steam/apps/{PLATFORM_ID}/header.jpg?t={NOW}';

    abstract protected function sqlTypeClause(): string;

    public function __construct(private readonly IDatabaseService $databaseService) {}

    public function getLookUpURL(string $platformId): string
    {
        return str_replace('{PLATFORM_ID}', $platformId, self::LOOKUP_URL);
    }

    private function getCoverUrl(string $platformId, int $time): string
    {
        return str_replace(['{PLATFORM_ID}', '{NOW}'], [$platformId, $time], self::CAPSULE_URL);
    }

    protected function queryAPI(string $url, array $queryParams): array
    {
        return [];
    }

    public function search(string $query, ?int $startAt = null): array
    {
        $dbConn = $this->databaseService->open();

        $sql = "SELECT `app_id`, `name`
                    FROM `steam_products`
                    WHERE `name` LIKE ? AND `type` {$this->sqlTypeClause()}";
        $values = ["%{$query}%"];

        if (($dbResults = $dbConn->get($sql, \PDO::FETCH_ASSOC, $values)) === false) {
            throw new PlatformHelperException("A database error has occured");
        }

        $results = [];
        $queryLength = strlen($query);
        $queryWords = self::splitWords($query);
        foreach ($dbResults as $dbResult) {
            $id = $dbResult['app_id'];
            $imgUrl = $this->getCoverUrl($id, time());

            $results[] = [
                new PlatformAlbum($dbResult['name'], $id, ['n/a'], $imgUrl),
                self::computeDistance($dbResult['name'], $queryWords, $queryLength)
            ];
        }
        self::sortByDistance($results);

        return array_map(fn($item) => $item[0], $results);
    }

    public function searchExactMatch(string $title, array $ignoredArtists): ?array
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

    public function getAlbumDetails(string $albumId): ?PlatformAlbum
    {
        return null;
    }

    public function supportsPagination(): bool
    {
        return false;
    }

    public function nextPageStart(): ?int
    {
        return null;
    }

    public function resultsPerPage(): int
    {
        return -1;
    }
}
