<?php

namespace PlatformHelpers;

use chsxf\MFX\HttpStatusCodes;
use JsonException;
use Platform;
use PlatformAlbum;

final class SpotifyHelper extends AbstractAuthPlatformHelper
{
    use SearchExactMatchTrait;

    private const string API_SEARCH_URL = 'https://api.spotify.com/v1/search';
    private const string API_ALBUM_URL = 'https://api.spotify.com/v1/albums/' . AbstractPlatformHelper::PLATFORM_ID_PLACEHOLDER;
    private const string ALBUM_LOOKUP_URL = "https://open.spotify.com/album/" . AbstractPlatformHelper::PLATFORM_ID_PLACEHOLDER;

    private ?int $nextPageIndex = null;

    public function getPlatform(): Platform
    {
        return Platform::spotify;
    }

    public function getLookUpURL(string $platformId): string
    {
        return str_replace(AbstractPlatformHelper::PLATFORM_ID_PLACEHOLDER, $platformId, self::ALBUM_LOOKUP_URL);
    }

    protected function queryAPI(string $url, array $queryParams): array
    {
        $accessToken = $this->getAccessToken();

        if (!empty($queryParams)) {
            $queryString = http_build_query($queryParams);
            $url = sprintf("%s?%s", $url, $queryString);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ["Authorization: Bearer {$accessToken}"]
        ]);
        $result = curl_exec($ch);
        if ($result === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new PlatformHelperException($error);
        } else if (($http_status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE)) != 200) {
            throw new PlatformHelperException("Server responded with HTTP status code {$http_status}", HttpStatusCodes::tryFrom($http_status));
        }
        curl_close($ch);

        try {
            $decodedJson = json_decode($result, JSON_THROW_ON_ERROR | JSON_OBJECT_AS_ARRAY);
        } catch (JsonException $e) {
            throw new PlatformHelperException('An error has occured while parsing search results.', previous: $e);
        }

        return $decodedJson;
    }

    public function search(string $query, ?int $startAt = null): array
    {
        $queryParams = [
            'q' => $query,
            'type' => 'album',
            'market' => 'US',
            'limit' => $this->resultsPerPage(),
            'offset' => $startAt ?? 0
        ];
        $decodedJson = $this->queryAPI(self::API_SEARCH_URL, $queryParams);

        if (array_key_exists('next', $decodedJson['albums'])) {
            $queryParams = parse_url($decodedJson['albums']['next'], PHP_URL_QUERY);
            if (!empty($queryParams)) {
                parse_str($queryParams, $parsedQueryParams);
                if (array_key_exists('offset', $parsedQueryParams) && ctype_digit($parsedQueryParams['offset'])) {
                    $this->nextPageIndex = intval($parsedQueryParams['offset']);
                }
            }
        }

        $results = [];
        foreach ($decodedJson['albums']['items'] as $album) {
            $artists = array_map(fn($item) => $item['name'], $album['artists']);
            $results[] = new PlatformAlbum($album['name'], $album['id'], $artists, $album['images'][0]['url']);
        }
        return $results;
    }

    public function getAlbumDetails(string $albumId): PlatformAlbum|false|null
    {
        $url = str_replace(AbstractPlatformHelper::PLATFORM_ID_PLACEHOLDER, $albumId, self::API_ALBUM_URL);
        $decodedJson = $this->queryAPI($url, []);

        $artists = [];
        foreach ($decodedJson['artists'] as $artist) {
            $artists[] = $artist['name'];
        }
        return new PlatformAlbum($decodedJson['name'], $albumId, $artists, $decodedJson['images'][0]['url']);
    }

    protected function fetchAccessToken(): AuthAccessTokenData
    {
        $postFields = http_build_query([
            'grant_type' => 'client_credentials',
            'client_id' => $this->getClientId(),
            'client_secret' => $this->getClientSecret()
        ]);

        $ch = curl_init('https://accounts.spotify.com/api/token');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_RETURNTRANSFER => true
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
            throw new PlatformHelperException('Unable to parse token result', previous: $e);
        }
        return new AuthAccessTokenData($decodedJson['access_token'], intval($decodedJson['expires_in']));
    }

    protected function getClientId(): string
    {
        return $this->configService->getValue('spotify.client_id');
    }

    protected function getClientSecret(): string
    {
        return $this->configService->getValue('spotify.client_secret');
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
        return 50;
    }
}
