<?php

namespace PlatformHelpers;

use chsxf\MFX\Services\ICoreServiceProvider;
use Platform;

final class PlatformHelperFactory
{
    public static function get(Platform $platform, ICoreServiceProvider $serviceProvider): ?IPlatformHelper
    {
        switch ($platform) {
            case Platform::appleMusic:
                return new AppleMusicHelper($serviceProvider->getConfigService());
            case Platform::bandcamp:
                return new BandcampPlatformHelper();
            case Platform::deezer:
                return new DeezerPlatformHelper();
            case Platform::spotify:
                return new SpotifyHelper($serviceProvider->getConfigService(), $serviceProvider->getDatabaseService(), $serviceProvider->getAuthenticationService());
            default:
                throw new PlatformHelperException("Unsupported platform '{$platform->value}'");
        }
    }
}
