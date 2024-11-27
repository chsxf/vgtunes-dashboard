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

            $query = http_build_query([
                'term' => $validator['title'],
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

            $candidates = [];

            $decodedJson = json_decode($result, JSON_THROW_ON_ERROR | JSON_OBJECT_AS_ARRAY);
            foreach ($decodedJson['results']['albums']['data'] as $album) {
                $candidate = ['id' => $album['id'], 'title' => $album['attributes']['name'], 'artist' => $album['attributes']['artistName']];
                if ($candidate['title'] == $validator['title'] && stripos($candidate['artist'], $validator['artist']) !== false) {
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
            trigger_error($e->getMessage(), E_USER_ERROR);
        }

        return RequestResult::buildJSONRequestResult($requestResultData, statusCode: $statusCode);
    }
}
