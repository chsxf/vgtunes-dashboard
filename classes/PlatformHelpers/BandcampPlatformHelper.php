<?php

namespace PlatformHelpers;

use chsxf\MFX\HttpStatusCodes;
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

    public function search(string $query, ?int $startAt = null): array
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
        } else if (($http_status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE)) !== 200) {
            $httpStatusCode = HttpStatusCodes::tryFrom($http_status);
            throw new PlatformHelperException("Server responded with HTTP status code {$http_status}", $httpStatusCode);
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
            $filename = preg_replace('/_\d+\.jpg$/', '_20.jpg', $filename);
            $pathWoFilename = dirname($url['path']);
            $newPath = "{$pathWoFilename}/a{$filename}";
            $imgUrl = "{$url['scheme']}://{$url['host']}{$newPath}";

            $results[] = new PlatformAlbum($album['name'], $id, [$album['band_name']], $imgUrl);
        }
        return $results;
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
