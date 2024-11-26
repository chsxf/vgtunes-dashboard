<?php

use chsxf\MFX\Attributes\RequiredRequestMethod;
use chsxf\MFX\Attributes\Route;
use chsxf\MFX\DatabaseConnectionInstance;
use chsxf\MFX\DataValidator;
use chsxf\MFX\DataValidator\FieldType;
use chsxf\MFX\HttpStatusCodes;
use chsxf\MFX\RequestMethod;
use chsxf\MFX\RequestResult;
use chsxf\MFX\Routers\BaseRouteProvider;

final class Suggestions extends BaseRouteProvider
{
    private const DEEZER_API_TEMPLATE_URL = "https://api.deezer.com/search/album?q={ALBUM_NAME}";

    #[Route, RequiredRequestMethod(RequestMethod::GET)]
    public static function album(array $params): RequestResult
    {
        $albumSearchQuery = trim($params[0] ?? '');
        if (empty($albumSearchQuery)) {
            trigger_error('An album query is required');
            return RequestResult::buildJSONRequestResult([]);
        }

        $url = str_replace('{ALBUM_NAME}', urlencode($albumSearchQuery), self::DEEZER_API_TEMPLATE_URL);

        $rawSearchResults = file_get_contents($url);
        try {
            $decodedSearchResults = json_decode($rawSearchResults, flags: JSON_THROW_ON_ERROR | JSON_OBJECT_AS_ARRAY);
        } catch (JsonException $e) {
            trigger_error("An error has occured while decoding JSON data: {$e->getMessage()}");
            return RequestResult::buildJSONRequestResult([]);
        }

        $entries = [];
        foreach ($decodedSearchResults['data'] as $entry) {
            $entries[] = [
                'title' => $entry['title'],
                'cover' => $entry['cover_xl'],
                'artist' => $entry['artist']['name'],
                'instances' => [
                    'deezer' => $entry['id']
                ]
            ];
        }

        $results = ['entries' => $entries];
        return RequestResult::buildJSONRequestResult($results);
    }

    #[Route, RequiredRequestMethod(RequestMethod::GET)]
    public function querySpotify(): RequestResult
    {
        $validator = new DataValidator();
        $validator->createField('title', FieldType::TEXT);
        $validator->createField('artist', FieldType::TEXT);

        $requestResultData = [];
        $statusCode = HttpStatusCodes::ok;

        $dbConn = null;

        try {
            if (!$validator->validate($_GET)) {
                throw new Exception('Invalid parameters');
            }

            $dbConn = $this->serviceProvider->getDatabaseService()->open();
            $accessToken = $this->getAccessToken($dbConn);

            $query = http_build_query([
                'q' => $validator['title'],
                'type' => 'album',
                'market' => 'US',
                'limit' => 50
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
                throw new Exception($error);
            }
            curl_close($ch);

            $candidates = [];

            $decodedJson = json_decode($result, JSON_THROW_ON_ERROR | JSON_OBJECT_AS_ARRAY);
            foreach ($decodedJson['albums']['items'] as $album) {
                $candidate = ['id' => $album['id'], 'title' => $album['name'], 'artist' => $album['artists'][0]['name']];
                if ($candidate['title'] == $validator['title'] && $candidate['artist'] == $validator['artist']) {
                    $requestResultData['exactMatch'] = true;
                    $requestResultData['id'] = $candidate['id'];
                    break;
                }
                $candidates[] = $candidate;
            }

            if (empty($requestResultData['exactMatch'])) {
                $requestResultData['exactMatch'] = false;
                $requestResultData['candidates'] = $candidates;
            }
        } catch (Exception $e) {
            if ($dbConn !== null) {
                $this->serviceProvider->getDatabaseService()->close($dbConn);
            }

            trigger_error($e->getMessage(), E_USER_ERROR);
        }

        return RequestResult::buildJSONRequestResult($requestResultData, statusCode: $statusCode);
    }

    private function getAccessToken(DatabaseConnectionInstance $dbConn): string
    {
        $dbConn->beginTransaction();

        $user = $this->serviceProvider->getAuthenticationService()->getCurrentAuthenticatedUser();

        $sql = "SELECT `access_token` FROM `spotify_access_tokens` WHERE `user_id` = ? AND `expires_at` > CURRENT_TIMESTAMP()";
        if (($accessToken = $dbConn->getValue($sql, $user->getId())) !== false) {
            $dbConn->rollBack();
            return $accessToken;
        }

        try {
            $config = $this->serviceProvider->getConfigService();
            $newAccessToken = SpotifyHelpers::fetchAccessToken($config->getValue('spotify.client_id'), $config->getValue('spotify.client_secret'));
        } catch (Exception $e) {
            $dbConn->rollBack();
            throw new Exception("Issue generating new Spotify access token", previous: $e);
        }

        $sql = "INSERT INTO `spotify_access_tokens` (`user_id`, `access_token`, `expires_at`) VALUE (?, ?, DATE_ADD(CURRENT_TIMESTAMP(), INTERVAL 1 HOUR))
                    ON DUPLICATE KEY UPDATE `access_token` = ?, `expires_at` = DATE_ADD(CURRENT_TIMESTAMP(), INTERVAL 1 HOUR)";
        if ($dbConn->exec($sql, $user->getId(), $newAccessToken, $newAccessToken) === false) {
            $dbConn->rollBack();
            throw new Exception('Unable to update Spotify access token');
        }

        $dbConn->commit();
        return $newAccessToken;
    }
}
