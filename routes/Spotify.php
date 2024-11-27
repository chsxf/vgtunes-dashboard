<?php

use chsxf\MFX\Attributes\RequiredRequestMethod;
use chsxf\MFX\Attributes\Route;
use chsxf\MFX\DataValidator;
use chsxf\MFX\DataValidator\FieldType;
use chsxf\MFX\HttpStatusCodes;
use chsxf\MFX\RequestMethod;
use chsxf\MFX\RequestResult;
use chsxf\MFX\Routers\BaseRouteProvider;

final class Spotify extends BaseRouteProvider
{
    #[Route, RequiredRequestMethod(RequestMethod::GET)]
    public function query(): RequestResult
    {
        $validator = new DataValidator();
        $validator->createField('title', FieldType::TEXT);
        $validator->createField('artist', FieldType::TEXT);

        $requestResultData = [];
        $statusCode = HttpStatusCodes::ok;

        try {
            if (!$validator->validate($_GET)) {
                throw new Exception('Invalid parameters');
            }

            $accessToken = $this->getAccessToken();

            $queryResult = self::queryAPI($accessToken, $validator['title'], $validator['artist']);
            if ($queryResult['exactMatch'] === false && empty($queryResult['candidates'])) {
                $cleanedTitle = trim(preg_replace('/\([^)]+\)/', '', $validator['title']));
                $queryResult = self::queryAPI($accessToken, $cleanedTitle, $validator['artists']);
            }

            $requestResultData = array_merge($requestResultData, $queryResult);
        } catch (Exception $e) {
            trigger_error($e->getMessage(), E_USER_ERROR);
        }

        return RequestResult::buildJSONRequestResult($requestResultData, statusCode: $statusCode);
    }

    private static function queryAPI(string $accessToken, string $title, string $artist): array
    {
        $query = http_build_query([
            'q' => $title,
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

        $returnValue = [];

        $candidates = [];

        $decodedJson = json_decode($result, JSON_THROW_ON_ERROR | JSON_OBJECT_AS_ARRAY);
        foreach ($decodedJson['albums']['items'] as $album) {
            $candidate = ['id' => $album['id'], 'title' => $album['name'], 'artist' => $album['artists'][0]['name']];
            if ($candidate['title'] == $title && $candidate['artist'] == $artist) {
                $returnValue['exactMatch'] = true;
                $returnValue['candidate'] = $candidate;
                break;
            }
            $candidates[] = $candidate;
        }

        if (empty($returnValue['exactMatch'])) {
            $returnValue['exactMatch'] = false;
            $returnValue['candidates'] = $candidates;
        }

        return $returnValue;
    }

    private function getAccessToken(): string
    {
        $dbService = $this->serviceProvider->getDatabaseService();
        $dbConn = $dbService->open();
        $dbConn->beginTransaction();

        $user = $this->serviceProvider->getAuthenticationService()->getCurrentAuthenticatedUser();

        $sql = "SELECT `access_token` FROM `spotify_access_tokens` WHERE `user_id` = ? AND `expires_at` > CURRENT_TIMESTAMP()";
        if (($accessToken = $dbConn->getValue($sql, $user->getId())) !== false) {
            $dbConn->rollBack();
            $dbService->close($dbConn);
            return $accessToken;
        }

        try {
            $config = $this->serviceProvider->getConfigService();
            $newAccessToken = SpotifyHelpers::fetchAccessToken($config->getValue('spotify.client_id'), $config->getValue('spotify.client_secret'));
        } catch (Exception $e) {
            $dbConn->rollBack();
            $dbService->close($dbConn);
            throw new Exception("Issue generating new Spotify access token", previous: $e);
        }

        $sql = "INSERT INTO `spotify_access_tokens` (`user_id`, `access_token`, `expires_at`) VALUE (?, ?, DATE_ADD(CURRENT_TIMESTAMP(), INTERVAL 1 HOUR))
                    ON DUPLICATE KEY UPDATE `access_token` = ?, `expires_at` = DATE_ADD(CURRENT_TIMESTAMP(), INTERVAL 1 HOUR)";
        if ($dbConn->exec($sql, $user->getId(), $newAccessToken, $newAccessToken) === false) {
            $dbConn->rollBack();
            $dbService->close($dbConn);
            throw new Exception('Unable to update Spotify access token');
        }

        $dbConn->commit();
        $dbService->close($dbConn);
        return $newAccessToken;
    }
}
