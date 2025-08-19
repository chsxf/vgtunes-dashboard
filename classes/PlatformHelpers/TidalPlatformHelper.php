<?php

namespace PlatformHelpers;

use chsxf\MFX\HttpStatusCodes;
use JsonException;
use Platform;
use PlatformAlbum;

final class TidalPlatformHelper extends AbstractAuthPlatformHelper
{
    use SearchExactMatchTrait, DistanceResultSorterTrait;

    private const string API_SEARCH_URL = 'https://openapi.tidal.com/v2/searchResults/{QUERY}';
    private const string API_MULTIPLE_ALBUMS_URL = 'https://openapi.tidal.com/v2/albums';
    private const string API_ALBUM_URL = 'https://openapi.tidal.com/v2/albums/' . AbstractPlatformHelper::PLATFORM_ID_PLACEHOLDER;
    private const string ALBUM_LOOKUP_URL = "https://tidal.com/album/" . AbstractPlatformHelper::PLATFORM_ID_PLACEHOLDER;

    public function getPlatform(): Platform
    {
        return Platform::tidal;
    }

    public function getLookUpURL(string $platformId): string
    {
        return str_replace(AbstractPlatformHelper::PLATFORM_ID_PLACEHOLDER, $platformId, self::ALBUM_LOOKUP_URL);
    }

    private static function custom_http_build_query(array $params): string
    {
        $queryMembers = [];
        foreach ($params as $k => $v) {
            if (is_array($v)) {
                foreach ($v as $vi) {
                    $queryMembers[] = sprintf("%s=%s", rawurlencode($k), rawurlencode($vi));
                }
            } else {
                $queryMembers[] = sprintf("%s=%s", rawurlencode($k), rawurlencode($v));
            }
        }
        return implode('&', $queryMembers);
    }

    protected function queryAPI(string $url, array $queryParams): array
    {
        $accessToken = $this->getAccessToken();

        $queryString = self::custom_http_build_query($queryParams);
        $url = sprintf("%s?%s", $url, $queryString);
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
        $encodedQquery = rawurlencode($query);
        $url = str_replace('{QUERY}', $encodedQquery, self::API_SEARCH_URL);
        $queryParams = [
            'countryCode' => 'US',
            'explicitFilter' => 'include,exclude',
            'include' => 'albums'
        ];
        $decodedJson = $this->queryAPI($url, $queryParams);
        if (empty($decodedJson['included'])) {
            return [];
        }

        $results = [];
        $queryLength = strlen($query);
        $queryWords = self::splitWords($query);
        foreach ($decodedJson['included'] as $album) {
            if (in_array('STREAM', $album['attributes']['availability'])) {
                $title = $album['attributes']['title'];
                $distance = self::computeDistance($title, $queryWords, $queryLength);
                $albumDetails = [
                    'title' => $title,
                    'id' => $album['id']
                ];
                $results[$album['id']] = [$albumDetails, $distance];
            }
        }

        $queryParams = [
            'countryCode' => 'US',
            'include' => ['coverArt', 'artists'],
            'filter[id]' => array_keys($results)
        ];
        $decodedJson = $this->queryAPI(self::API_MULTIPLE_ALBUMS_URL, $queryParams);
        $included = $decodedJson['included'];
        foreach ($decodedJson['data'] as $album) {
            $albumId = $album['id'];

            $coverUrl = '';
            $coverArtId = $album['relationships']['coverArt']['data'][0]['id'];
            foreach ($included as $includedItem) {
                if ($includedItem['type'] == 'artworks' && $includedItem['id'] == $coverArtId) {
                    $coverUrl = $includedItem['attributes']['files'][0]['href'];
                }
            }

            $artistIds = array_map(fn($item) => $item['id'], $album['relationships']['artists']['data']);
            $artists = [];
            foreach ($included as $includedItem) {
                if ($includedItem['type'] == 'artists' && in_array($includedItem['id'], $artistIds)) {
                    $artists[] = $includedItem['attributes']['name'];
                }
            }

            $partialDetails = $results[$albumId][0];
            $results[$albumId][0] = new PlatformAlbum($partialDetails['title'], $albumId, $artists, $coverUrl);
        }

        $results = array_values($results);
        self::sortByDistance($results);

        return array_map(fn($item) => $item[0], $results);
    }

    public function getAlbumDetails(string $albumId): PlatformAlbum|false|null
    {
        $url = str_replace(AbstractPlatformHelper::PLATFORM_ID_PLACEHOLDER, $albumId, self::API_ALBUM_URL);
        $queryParams = [
            'countryCode' => 'US',
            'include' => ['artists', 'coverArt']
        ];
        $decodedDetails = $this->queryAPI($url, $queryParams);

        $title = $decodedDetails['data']['attributes']['title'];
        $artists = [];
        $coverUrl = '';

        foreach ($decodedDetails['included'] as $includedData) {
            switch ($includedData['type']) {
                case 'artworks':
                    $coverUrl = $includedData['attributes']['files'][0]['href'];
                    break;

                case 'artists':
                    $artists[] = $includedData['attributes']['name'];
                    break;
            }
        }

        return new PlatformAlbum($title, $albumId, $artists, $coverUrl);
    }

    protected function fetchAccessToken(): AuthAccessTokenData
    {
        $postFields = http_build_query(['grant_type' => 'client_credentials']);

        $credentials = base64_encode(sprintf("%s:%s", $this->getClientId(), $this->getClientSecret()));

        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
            "Authorization: Basic {$credentials}"
        ];

        $ch = curl_init('https://auth.tidal.com/v1/oauth2/token');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
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
        return $this->configService->getValue('tidal.client_id');
    }

    protected function getClientSecret(): string
    {
        return $this->configService->getValue('tidal.client_secret');
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
