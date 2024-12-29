<?php

namespace PlatformHelpers;

use chsxf\MFX\Services\IConfigService;
use chsxf\MFX\Services\ICoreServiceProvider;

final class PlatformHelperFactory
{
    public const APPLE_MUSIC = 'apple_music';
    public const DEEZER = 'deezer';
    public const SPOTIFY = 'spotify';

    public const array PLATFORMS = [
        self::APPLE_MUSIC => 'Apple Music',
        self::DEEZER => 'Deezer',
        self::SPOTIFY => 'Spotify'
    ];

    public static function get(string $platform, ICoreServiceProvider $serviceProvider): ?IPlatformHelper
    {
        switch ($platform) {
            case self::APPLE_MUSIC:
                return new AppleMusicHelper($serviceProvider->getConfigService());
            case self::DEEZER:
                return new DeezerPlatformHelper();
            case self::SPOTIFY:
                return new SpotifyHelper($serviceProvider->getConfigService(), $serviceProvider->getDatabaseService(), $serviceProvider->getAuthenticationService());
            default:
                throw new PlatformHelperException("Unsupported platform '{$platform}'");
        }
    }
}
