<?php

use chsxf\MFX\Attributes\RequiredRequestMethod;
use chsxf\MFX\Attributes\Route;
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
}
