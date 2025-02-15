<?php

namespace PlatformHelpers;

use JsonException;
use Platform;
use PlatformAlbum;

final class BandcampPlatformHelper implements IPlatformHelper
{
    use SearchExactMatchTrait;

    private const string SEARCH_URL = 'https://bandcamp.com/api/bcsearch_public_api/1/autocomplete_elastic';

    public function getPlatform(): Platform
    {
        return Platform::bandcamp;
    }

    public function getLookUpURL(string $platformId): string
    {
        $chunks = explode('|', $platformId);
        return $chunks[1];
    }

    public function search(string $query): array
    {
        $payload = [
            'search_text' => $query,
            'fan_id' => null,
            'full_page' => false,
            'search_filter' => 'a'
        ];
        $payload = json_encode($payload);

        $ch = curl_init(self::SEARCH_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => $payload
        ]);
        $result = curl_exec($ch);
        if ($result === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new PlatformHelperException($error);
        }
        curl_close($ch);

        try {
            $decodedJson = json_decode($result, JSON_THROW_ON_ERROR | JSON_OBJECT_AS_ARRAY);
        } catch (JsonException $e) {
            throw new PlatformHelperException('An error has occuped while parsing search results.', previous: $e);
        }

        $results = [];
        foreach ($decodedJson['auto']['results'] as $album) {
            $id = "{$album['id']}|{$album['item_url_path']}";

            $url = parse_url($album['img']);
            $filename = basename($url['path']);
            $pathWoFilename = dirname($url['path']);
            $newPath = "{$pathWoFilename}/a{$filename}";
            $imgUrl = "{$url['scheme']}://{$url['host']}{$newPath}";

            $results[] = new PlatformAlbum($album['name'], $id, $album['band_name'], $imgUrl);
        }
        return $results;
    }
}
