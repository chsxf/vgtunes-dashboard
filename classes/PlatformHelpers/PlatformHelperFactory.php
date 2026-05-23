<?php

namespace PlatformHelpers;

use chsxf\MFX\Services\ICoreServiceProvider;
use Exception;
use Platform;

final class PlatformHelperFactory
{
    private static ?array $options = null;

    public static function loadOptions(ICoreServiceProvider $serviceProvider): void
    {
        $dbService = $serviceProvider->getDatabaseService();
        $dbConn = $dbService->open();

        $sql = "SELECT * FROM `platform_helper_options`";
        if (($fetchedOptions = $dbConn->get($sql, \PDO::FETCH_ASSOC)) === false) {
            throw new Exception("Unable to fetch platform helpers options");
        }

        $optionMap = array_map(self::getDefaultOptions(...), Platform::PLATFORMS);
        foreach ($fetchedOptions as $optionRow) {
            $platformKey = $optionRow['platform'];
            if (!array_key_exists($platformKey, $optionMap)) {
                continue;
            }

            $optionKey = $optionRow['option'];
            $optionMap[$platformKey][$optionKey] = self::filterOptionValue($optionKey, $optionRow['value']);
        }
        self::$options = $optionMap;
    }

    public static function getOptions(): array
    {
        if (self::$options === null) {
            throw new Exception("Options must be loaded first");
        }
        return self::$options;
    }

    private static function getDefaultOptions(): array
    {
        return [
            AbstractPlatformHelper::PLATFORM_OPTION_ENABLED => true
        ];
    }

    private static function filterOptionValue(string $option, ?string $value): mixed
    {
        switch ($option) {
            case AbstractPlatformHelper::PLATFORM_OPTION_ENABLED:
                return !empty($value);

            default:
                return $value;
        }
    }

    public static function isPlatformEnabled(Platform $platform): bool
    {
        $platformOptions = self::$options[$platform->value] ?? self::getDefaultOptions();
        return $platformOptions[AbstractPlatformHelper::PLATFORM_OPTION_ENABLED];
    }

    public static function getDisabledHelperCount(): int
    {
        $total = 0;
        array_walk(self::$options, function ($value, $key) use (&$total) {
            if ($value[AbstractPlatformHelper::PLATFORM_OPTION_ENABLED] === false) {
                $total++;
            }
        });
        return $total;
    }

    public static function get(Platform $platform, ICoreServiceProvider $serviceProvider): ?AbstractPlatformHelper
    {
        $platformOptions = self::$options[$platform->value] ?? self::getDefaultOptions();

        switch ($platform) {
            case Platform::appleMusic:
                return new AppleMusicHelper($platformOptions, $serviceProvider->getConfigService());
            case Platform::bandcamp:
                return new BandcampPlatformHelper($platformOptions);
            case Platform::deezer:
                return new DeezerPlatformHelper($platformOptions);
            case Platform::spotify:
                return new SpotifyHelper($platformOptions, $serviceProvider->getConfigService(), $serviceProvider->getDatabaseService(), $serviceProvider->getAuthenticationService());
            case Platform::steamGame:
                return new SteamGamePlatformHelper($platformOptions, $serviceProvider->getDatabaseService());
            case Platform::steamSoundtrack:
                return new SteamSoundtrackPlatformHelper($platformOptions, $serviceProvider->getDatabaseService());
            case Platform::tidal:
                return new TidalPlatformHelper($platformOptions, $serviceProvider->getConfigService(), $serviceProvider->getDatabaseService(), $serviceProvider->getAuthenticationService());
            default:
                throw new PlatformHelperException("Unsupported platform '{$platform->value}'");
        }
    }
}
