<?php

namespace PlatformHelpers;

use JsonException;
use Platform;
use PlatformAlbum;

final class DeezerPlatformHelper implements IPlatformHelper
{
    use SearchExactMatchTrait;

    private const DEEZER_API_TEMPLATE_URL = "https://api.deezer.com/search/album";
    private const DEEZER_ALBUM_LOOKUP_URL = "https://www.deezer.com/fr/album/{PLATFORM_ID}";

    private ?int $nextPageIndex = null;

    public function getPlatform(): Platform
    {
        return Platform::deezer;
    }

    public function getLookUpURL(string $platformId): string
    {
        return str_replace('{PLATFORM_ID}', $platformId, self::DEEZER_ALBUM_LOOKUP_URL);
    }

    public function search(string $query, ?int $startAt = null): array
    {
        if (empty($query)) {
            throw new PlatformHelperException('Query string cannot be empty.');
        }

        $buildQuery = ['q' => $query, 'limit' => $this->resultsPerPage(), 'index' => $startAt ?? 0];
        $url = sprintf("%s?%s", self::DEEZER_API_TEMPLATE_URL, http_build_query($buildQuery));

        $rawSearchResults = file_get_contents($url);
        try {
            $decodedSearchResults = json_decode($rawSearchResults, flags: JSON_THROW_ON_ERROR | JSON_OBJECT_AS_ARRAY);
        } catch (JsonException $e) {
            throw new PlatformHelperException('An error has occured while parsing search results.', previous: $e);
        }

        if (array_key_exists('next', $decodedSearchResults)) {
            $queryParams = parse_url($decodedSearchResults['next'], PHP_URL_QUERY);
            if (!empty($queryParams)) {
                parse_str($queryParams, $parsedQueryParams);
                if (array_key_exists('index', $parsedQueryParams) && ctype_digit($parsedQueryParams['index'])) {
                    $this->nextPageIndex = intval($parsedQueryParams['index']);
                }
            }
        }

        $entries = [];
        foreach ($decodedSearchResults['data'] as $entry) {
            $entries[] = new PlatformAlbum($entry['title'], $entry['id'], [$entry['artist']['name']], $entry['cover_xl']);
        }
        return $entries;
    }

    public function supportsPagination(): bool
    {
        return true;
    }

    public function nextPageStart(): ?int
    {
        return $this->nextPageIndex;
    }

    public function resultsPerPage(): int
    {
        return 100;
    }
}
