<?php

namespace PlatformHelpers;

final class AuthAccessTokenData
{
    public function __construct(public readonly string $token, public readonly int $expirationDelay) {}
}
