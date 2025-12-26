<?php

namespace PlatformHelpers;

use JsonException;
use Platform;
use PlatformAlbum;
use PlatformAvailability;

final class DeezerPlatformHelper extends AbstractPlatformHelper
{
    use SearchExactMatchTrait;

    private const string API_SEARCH_URL = "https://api.deezer.com/search/album";
    private const string API_ALBUM_URL = "https://api.deezer.com/album/" . AbstractPlatformHelper::PLATFORM_ID_PLACEHOLDER;
    private const string ALBUM_LOOKUP_URL = "https://www.deezer.com/fr/album/" . AbstractPlatformHelper::PLATFORM_ID_PLACEHOLDER;

    private ?int $nextPageIndex = null;

    public function getPlatform(): Platform
    {
        return Platform::deezer;
    }

    public function getLookUpURL(string $platformId): string
    {
        return str_replace(AbstractPlatformHelper::PLATFORM_ID_PLACEHOLDER, $platformId, self::ALBUM_LOOKUP_URL);
    }

    protected function queryAPI(string $url, array $queryParams): array
    {
        if (!empty($queryParams)) {
            $queryString = http_build_query($queryParams);
            $url = sprintf("%s?%s", $url, $queryString);
        }

        $rawResults = file_get_contents($url);
        try {
            $decodedJson = json_decode($rawResults, flags: JSON_THROW_ON_ERROR | JSON_OBJECT_AS_ARRAY);
        } catch (JsonException $e) {
            throw new PlatformHelperException('An error has occured while parsing search results.', previous: $e);
        }

        return $decodedJson;
    }

    public function search(string $query, ?int $startAt = null): array
    {
        if (empty($query)) {
            throw new PlatformHelperException('Query string cannot be empty.');
        }

        $buildQuery = [
            'q' => $query,
            'limit' => $this->resultsPerPage(),
            'index' => $startAt ?? 0
        ];
        $decodedSearchResults = $this->queryAPI(self::API_SEARCH_URL, $buildQuery);

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

    public function getAlbumDetails(string $albumId): PlatformAlbum|false|null
    {
        $url = str_replace(AbstractPlatformHelper::PLATFORM_ID_PLACEHOLDER, $albumId, self::API_ALBUM_URL);
        $decodedResult = $this->queryAPI($url, []);

        $artists = [];
        foreach ($decodedResult['contributors'] as $artist) {
            if ($artist['type'] == 'artist' && $artist['role'] == "Main") {
                $artists[] = $artist['name'];
            }
        }
        return new PlatformAlbum($decodedResult['title'], $albumId, $artists, $decodedResult['cover_xl']);
    }

    public function getAlbumAvailability(string $albumId): PlatformAvailability|false
    {
        $url = str_replace(AbstractPlatformHelper::PLATFORM_ID_PLACEHOLDER, $albumId, self::API_ALBUM_URL);
        $decodedResult = $this->queryAPI($url, []);

        if (array_key_exists('error', $decodedResult)) {
            $errorData = $decodedResult['error'];
            if (array_key_exists('code', $errorData)) {
                $code = $errorData['code'];
                if ($code == 800) {
                    return PlatformAvailability::NotAvailable;
                }
            }
        }

        return PlatformAvailability::Available;
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
