<?php

use Jose\Component\Core\AlgorithmManager;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\Algorithm\ES256;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer;

final class AppleMusicHelpers
{
    public static function createJsonWebToken(string $keyId, string $keyPath, string $providerId): string
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
}
