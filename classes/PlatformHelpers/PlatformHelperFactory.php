<?php

namespace PlatformHelpers;

use chsxf\MFX\Services\ICoreServiceProvider;
use Platform;

final class PlatformHelperFactory
{
    public static function get(Platform $platform, ICoreServiceProvider $serviceProvider): ?AbstractPlatformHelper
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
            case Platform::steamGame:
                return new SteamGamePlatformHelper($serviceProvider->getDatabaseService());
            case Platform::steamSoundtrack:
                return new SteamSoundtrackPlatformHelper($serviceProvider->getDatabaseService());
            case Platform::tidal:
                return new TidalPlatformHelper($serviceProvider->getConfigService(), $serviceProvider->getDatabaseService(), $serviceProvider->getAuthenticationService());
            default:
                throw new PlatformHelperException("Unsupported platform '{$platform->value}'");
        }
    }
}
