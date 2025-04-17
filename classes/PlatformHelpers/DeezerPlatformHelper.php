<?php

namespace PlatformHelpers;

use JsonException;
use Platform;
use PlatformAlbum;

final class DeezerPlatformHelper implements IPlatformHelper
{
    use SearchExactMatchTrait;

    private const string API_SEARCH_URL = "https://api.deezer.com/search/album";
    private const string API_ALBUM_URL = "https://api.deezer.com/album/" . IPlatformHelper::PLATFORM_ID_PLACEHOLDER;
    private const string ALBUM_LOOKUP_URL = "https://www.deezer.com/fr/album/" . IPlatformHelper::PLATFORM_ID_PLACEHOLDER;

    private ?int $nextPageIndex = null;

    public function getPlatform(): Platform
    {
        return Platform::deezer;
    }

    public function getLookUpURL(string $platformId): string
    {
        return str_replace(IPlatformHelper::PLATFORM_ID_PLACEHOLDER, $platformId, self::ALBUM_LOOKUP_URL);
    }

    public function search(string $query, ?int $startAt = null): array
    {
        if (empty($query)) {
            throw new PlatformHelperException('Query string cannot be empty.');
        }

        $buildQuery = ['q' => $query, 'limit' => $this->resultsPerPage(), 'index' => $startAt ?? 0];
        $url = sprintf("%s?%s", self::API_SEARCH_URL, http_build_query($buildQuery));

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

    public function getAlbumDetails(string $albumId): ?PlatformAlbum
    {
        $url = str_replace(IPlatformHelper::PLATFORM_ID_PLACEHOLDER, $albumId, self::API_ALBUM_URL);

        $rawResult = file_get_contents($url);
        try {
            $decodedResult = json_decode($rawResult, flags: JSON_THROW_ON_ERROR | JSON_OBJECT_AS_ARRAY);
        } catch (JsonException $e) {
            throw new PlatformHelperException('An error has occured while parsing album details', previous: $e);
        }

        $artists = [];
        foreach ($decodedResult['contributors'] as $artist) {
            if ($artist['type'] == 'artist' && $artist['role'] == "Main") {
                $artists[] = $artist['name'];
            }
        }
        return new PlatformAlbum($decodedResult['title'], $albumId, $artists, $decodedResult['cover_xl']);
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
