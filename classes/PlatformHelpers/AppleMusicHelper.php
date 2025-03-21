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

final class AppleMusicHelper implements IPlatformHelper
{
    use SearchExactMatchTrait;

    private const string APPLE_MUSIC_LOOKUP_URL = "https://music.apple.com/album/{PLATFORM_ID}";

    private ?int $nextPageIndex = null;

    public function __construct(private IConfigService $configService) {}

    public function getPlatform(): Platform
    {
        return Platform::appleMusic;
    }

    public function getLookUpURL(string $platformId): string
    {
        return str_replace('{PLATFORM_ID}', $platformId, self::APPLE_MUSIC_LOOKUP_URL);
    }

    public function search(string $query, ?int $startAt = null): array
    {
        $jsonWebToken = self::createJsonWebToken($this->configService->getValue('apple_music.key_id'), $this->configService->getValue('apple_music.key_path'), $this->configService->getValue('apple_music.team_id'));

        $query = http_build_query([
            'term' => $query,
            'limit' => $this->resultsPerPage(),
            'offset' => $startAt ?? 0,
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

                $results[] = new PlatformAlbum($albumAttributes['name'], $album['id'], $albumAttributes['artistName'], $coverUrl);
            }
        }
        return $results;
    }

    private static function createJsonWebToken(string $keyId, string $keyPath, string $providerId): string
    {
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
}
