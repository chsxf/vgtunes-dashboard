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

    private function getCoverUrl(string $platformId, int $time): string
    {
        return str_replace(['{PLATFORM_ID}', '{NOW}'], [$platformId, $time], self::CAPSULE_URL);
    }

    private static function containsExactWords(string $fullText, array $words): bool
    {
        $textWords = array_filter(preg_split('/\W/', strtolower($fullText)));
        $intersection = array_intersect($textWords, $words);
        return count($intersection) == count($words);
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
        $queryWords = array_filter(preg_split('/\W/', strtolower($query)));
        foreach ($dbResults as $dbResult) {
            $id = $dbResult['app_id'];
            $imgUrl = $this->getCoverUrl($id, time());

            if (self::containsExactWords($dbResult['name'], $queryWords)) {
                $lengthDistance = strlen($dbResult['name']) - strlen($queryLength);
            } else {
                $lengthDistance = PHP_INT_MAX;
            }
            $results[] = [new PlatformAlbum($dbResult['name'], $id, ['n/a'], $imgUrl), $lengthDistance];
        }
        usort($results, function ($itemA, $itemB) {
            $comp = $itemA[1] <=> $itemB[1];
            if ($comp === 0) {
                $comp = $itemA[0] <=> $itemB[0];
            }
            return $comp;
        });

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
