<?php

namespace PlatformHelpers;

use chsxf\MFX\HttpStatusCodes;
use chsxf\MFX\Services\IAuthenticationService;
use chsxf\MFX\Services\IConfigService;
use chsxf\MFX\Services\IDatabaseService;
use JsonException;
use Platform;
use PlatformAlbum;

final class SpotifyHelper implements IPlatformHelper
{
    use SearchExactMatchTrait;

    private const SPOTIFY_ALBUM_LOOKUP_URL = "https://open.spotify.com/album/{PLATFORM_ID}";

    private ?int $nextPageIndex = null;

    public function __construct(private IConfigService $configService, private IDatabaseService $databaseService, private IAuthenticationService $authService) {}

    public function getPlatform(): Platform
    {
        return Platform::spotify;
    }

    public function getLookUpURL(string $platformId): string
    {
        return str_replace('{PLATFORM_ID}', $platformId, self::SPOTIFY_ALBUM_LOOKUP_URL);
    }

    public function search(string $query, ?int $startAt = null): array
    {
        $accessToken = $this->getAccessToken();

        $query = http_build_query([
            'q' => $query,
            'type' => 'album',
            'market' => 'US',
            'limit' => $this->resultsPerPage(),
            'offset' => $startAt ?? 0
        ]);
        $url = "https://api.spotify.com/v1/search?{$query}";
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
            $results[] = new PlatformAlbum($album['name'], $album['id'], [$album['artists'][0]['name']], $album['images'][0]['url']);
        }
        return $results;
    }

    private static function fetchAccessToken(string $clientId, string $clientSecret): string
    {
        $postFields = http_build_query([
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret
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
        return $decodedJson['access_token'];
    }

    private function getAccessToken(): string
    {
        $dbConn = $this->databaseService->open();
        $dbConn->beginTransaction();

        $user = $this->authService->getCurrentAuthenticatedUser();

        $sql = "SELECT `access_token` FROM `spotify_access_tokens` WHERE `user_id` = ? AND `expires_at` > CURRENT_TIMESTAMP()";
        if (($accessToken = $dbConn->getValue($sql, $user->getId())) !== false) {
            $dbConn->rollBack();
            $this->databaseService->close($dbConn);
            return $accessToken;
        }

        try {
            $newAccessToken = self::fetchAccessToken($this->configService->getValue('spotify.client_id'), $this->configService->getValue('spotify.client_secret'));
        } catch (PlatformHelperException $e) {
            $dbConn->rollBack();
            $this->databaseService->close($dbConn);
            throw new PlatformHelperException("Issue generating new Spotify access token", previous: $e);
        }

        $sql = "INSERT INTO `spotify_access_tokens` (`user_id`, `access_token`, `expires_at`) VALUE (?, ?, DATE_ADD(CURRENT_TIMESTAMP(), INTERVAL 1 HOUR))
                    ON DUPLICATE KEY UPDATE `access_token` = ?, `expires_at` = DATE_ADD(CURRENT_TIMESTAMP(), INTERVAL 1 HOUR)";
        if ($dbConn->exec($sql, $user->getId(), $newAccessToken, $newAccessToken) === false) {
            $dbConn->rollBack();
            $this->databaseService->close($dbConn);
            throw new PlatformHelperException('Unable to update Spotify access token');
        }

        $dbConn->commit();
        $this->databaseService->close($dbConn);
        return $newAccessToken;
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
