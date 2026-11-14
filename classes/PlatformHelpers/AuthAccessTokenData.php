<?php

declare(strict_types=1);

namespace PlatformHelpers;

final class AuthAccessTokenData
{
    public function __construct(public readonly string $token, public readonly int $expirationDelay) {}
}
