<?php

namespace PlatformHelpers;

use JsonException;
use Platform;
use PlatformAlbum;

final class DeezerPlatformHelper implements IPlatformHelper
{
    use SearchExactMatchTrait;

    private const DEEZER_API_TEMPLATE_URL = "https://api.deezer.com/search/album?q={ALBUM_NAME}";
    private const DEEZER_ALBUM_LOOKUP_URL = "https://www.deezer.com/fr/album/{PLATFORM_ID}";

    public function getPlatform(): Platform
    {
        return Platform::deezer;
    }

    public function getLookUpURL(string $platformId): string
    {
        return str_replace('{PLATFORM_ID}', $platformId, self::DEEZER_ALBUM_LOOKUP_URL);
    }

    public function search(string $query): array
    {
        if (empty($query)) {
            throw new PlatformHelperException('Query string cannot be empty.');
        }

        $url = str_replace('{ALBUM_NAME}', urlencode($query), self::DEEZER_API_TEMPLATE_URL);

        $rawSearchResults = file_get_contents($url);
        try {
            $decodedSearchResults = json_decode($rawSearchResults, flags: JSON_THROW_ON_ERROR | JSON_OBJECT_AS_ARRAY);
        } catch (JsonException $e) {
            throw new PlatformHelperException('An error has occured while parsing search results.', previous: $e);
        }

        $entries = [];
        foreach ($decodedSearchResults['data'] as $entry) {
            $entries[] = new PlatformAlbum($entry['title'], $entry['id'], $entry['artist']['name'], $entry['cover_xl']);
        }
        return $entries;
    }
}
