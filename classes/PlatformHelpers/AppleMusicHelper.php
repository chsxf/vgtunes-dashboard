<?php

namespace PlatformHelpers;

use chsxf\MFX\HttpStatusCodes;
use chsxf\MFX\Services\IConfigService;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\Algorithm\ES256;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer;
use JsonException;
use Platform;
use PlatformAlbum;

// https://developer.apple.com/documentation/applemusicapi
final class AppleMusicHelper extends AbstractPlatformHelper
{
    private const string API_SEARCH_URL = "https://api.music.apple.com/v1/catalog/us/search";
    private const string API_ALBUM_URL = "https://api.music.apple.com/v1/catalog/us/albums/" . AbstractPlatformHelper::PLATFORM_ID_PLACEHOLDER;
    private const string ALBUM_LOOKUP_URL = "https://music.apple.com/album/" . AbstractPlatformHelper::PLATFORM_ID_PLACEHOLDER;

    private ?int $nextPageIndex = null;

    public function __construct(private IConfigService $configService) {}

    public function getPlatform(): Platform
    {
        return Platform::appleMusic;
    }

    public function getLookUpURL(string $platformId): string
    {
        return str_replace(AbstractPlatformHelper::PLATFORM_ID_PLACEHOLDER, $platformId, self::ALBUM_LOOKUP_URL);
    }

    protected function queryAPI(string $url, array $queryParams): array
    {
        $jsonWebToken = $this->createJsonWebToken();

        $queryString = http_build_query($queryParams);
        $url = sprintf("%s?%s", $url, $queryString);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ["Authorization: Bearer {$jsonWebToken}"]
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
        $query = [
            'term' => $query,
            'limit' => $this->resultsPerPage(),
            'offset' => $startAt ?? 0,
            'types' => 'albums'
        ];
        $decodedJson = $this->queryAPI(self::API_SEARCH_URL, $query);

        $results = [];
        if (!empty($decodedJson['results']) && !empty($decodedJson['results']['albums'])) {
            if (array_key_exists('next', $decodedJson['results']['albums'])) {
                $queryParams = parse_url($decodedJson['results']['albums']['next'], PHP_URL_QUERY);
                if (!empty($queryParams)) {
                    parse_str($queryParams, $parsedQueryParams);
                    if (array_key_exists('offset', $parsedQueryParams) && ctype_digit($parsedQueryParams['offset'])) {
                        $this->nextPageIndex = intval($parsedQueryParams['offset']);
                    }
                }
            }

            foreach ($decodedJson['results']['albums']['data'] as $album) {
                $albumAttributes = $album['attributes'];

                $coverUrl = $albumAttributes['artwork']['url'];
                $coverUrl = str_replace(['{w}', '{h}'], 1000, $coverUrl);

                $results[] = new PlatformAlbum($albumAttributes['name'], $album['id'], [$albumAttributes['artistName']], $coverUrl);
            }
        }
        return $results;
    }

    public function searchExactMatch(string $title, array $artists): ?array
    {
        $query = $title;

        foreach (PlatformAlbum::CLEAN_REGEXP as $replacementRegex) {
            if ($replacementRegex !== null) {
                $query = trim(preg_replace($replacementRegex, '', $query));
            }

            $passQueryResults = $this->search($query);
            foreach ($passQueryResults as $result) {
                $sameTitle = stripos($result->title, $query) === 0;

                $sameArtists = false;
                if (!empty($artists)) {
                    $joinedArtists = implode(' & ', $artists);
                    $sameArtists = strcasecmp($result->artists[0], $joinedArtists) === 0;
                }

                if ($sameTitle && $sameArtists) {
                    return iterator_to_array($result);
                }
            }
        }

        return null;
    }

    public function getAlbumDetails(string $albumId): PlatformAlbum|false|null
    {
        $url = str_replace(AbstractPlatformHelper::PLATFORM_ID_PLACEHOLDER, $albumId, self::API_ALBUM_URL);
        $decodedJson = $this->queryAPI($url, ['include' => 'artists']);

        $albumDataContainer = $decodedJson['data'][0];

        $artists = [];
        foreach ($albumDataContainer['relationships']['artists']['data'] as $artist) {
            if ($artist['type'] == 'artists') {
                $artists[] = $artist['attributes']['name'];
            }
        }

        $coverUrl = str_replace(['{w}', '{h}'], 1000, $albumDataContainer['attributes']['artwork']['url']);
        return new PlatformAlbum($albumDataContainer['attributes']['name'], $albumId, $artists, $coverUrl);
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
        return 25;
    }

    private function createJsonWebToken(): string
    {
        $keyId = $this->configService->getValue('apple_music.key_id');
        $keyPath = $this->configService->getValue('apple_music.key_path');
        $providerId = $this->configService->getValue('apple_music.team_id');

        $jwk = JWKFactory::createFromKeyFile($keyPath, null, ['use' => 'sig']);

        $header = ['alg' => 'ES256', 'kid' => $keyId];
        $payload = json_encode(['iss' => $providerId, 'iat' => time(), 'exp' => time() + 3600]);

        $algorithManager = new AlgorithmManager([new ES256()]);
        $jwsBuilder = new JWSBuilder($algorithManager);

        $jws = $jwsBuilder
            ->create()
            ->withPayload($payload)
            ->addSignature($jwk, $header)
            ->build();

        $serializer = new CompactSerializer();
        $token = $serializer->serialize($jws, 0);

        return $token;
    }
}
