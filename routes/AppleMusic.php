<?php

use chsxf\MFX\Attributes\RequiredRequestMethod;
use chsxf\MFX\Attributes\Route;
use chsxf\MFX\DataValidator;
use chsxf\MFX\DataValidator\FieldType;
use chsxf\MFX\HttpStatusCodes;
use chsxf\MFX\RequestMethod;
use chsxf\MFX\RequestResult;
use chsxf\MFX\Routers\BaseRouteProvider;

final class AppleMusic extends BaseRouteProvider
{
    #[Route, RequiredRequestMethod(RequestMethod::GET)]
    public function query(): RequestResult
    {
        $config = $this->serviceProvider->getConfigService();

        $validator = new DataValidator();
        $validator->createField('title', FieldType::TEXT);
        $validator->createField('artist', FieldType::TEXT);

        $requestResultData = [];
        $statusCode = HttpStatusCodes::ok;

        $jsonWebToken = AppleMusicHelpers::createJsonWebToken($config->getValue('apple_music.key_id'), $config->getValue('apple_music.key_path'), $config->getValue('apple_music.team_id'));

        try {
            if (!$validator->validate($_GET)) {
                throw new Exception('Invalid parameters');
            }

            $queryResult = self::queryAPI($jsonWebToken, $validator['title'], $validator['artist']);
            if ($queryResult['exactMatch'] === false && empty($queryResult['candidates'])) {
                $cleanedTitle = trim(preg_replace('/\([^)]+\)/', '', $validator['title']));
                $queryResult = self::queryAPI($jsonWebToken, $cleanedTitle, $validator['artist']);
            }

            $requestResultData = array_merge($requestResultData, $queryResult);
        } catch (Exception $e) {
            trigger_error($e->getMessage(), E_USER_ERROR);
        }

        return RequestResult::buildJSONRequestResult($requestResultData, statusCode: $statusCode);
    }

    private static function queryAPI(string $jsonWebToken, string $title, string $artist): array
    {
        $query = http_build_query([
            'term' => $title,
            'limit' => 25,
            'types' => 'albums'
        ]);

        $url = "https://api.music.apple.com/v1/catalog/us/search?{$query}";
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ["Authorization: Bearer {$jsonWebToken}"]
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
        if (!empty($decodedJson['results']) && !empty($decodedJson['results']['albums'])) {
            foreach ($decodedJson['results']['albums']['data'] as $album) {
                $candidate = ['id' => $album['id'], 'title' => $album['attributes']['name'], 'artist' => $album['attributes']['artistName']];
                if ($candidate['title'] == $title && stripos($candidate['artist'], $artist) !== false) {
                    $returnValue['exactMatch'] = true;
                    $returnValue['candidate'] = $candidate;
                    break;
                }
                $candidates[] = $candidate;
            }
        }

        if (empty($returnValue['exactMatch'])) {
            $returnValue['exactMatch'] = false;
            $returnValue['candidates'] = $candidates;
        }

        return $returnValue;
    }
}
