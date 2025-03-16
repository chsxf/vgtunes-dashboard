<?php

namespace PlatformHelpers;

use AutomatedActions\SteamProductType;
use Platform;

class SteamGamePlatformHelper extends AbstractSteamPlatformHelper
{
    private const string HERO_CAPSULE_URL = 'https://shared.cloudflare.steamstatic.com/store_item_assets/steam/apps/{PLATFORM_ID}/hero_capsule.jpg?t={NOW}';

    protected function sqlTypeClause(): string
    {
        return sprintf("IN ('%s','%s')", SteamProductType::game->value, SteamProductType::dlc->value);
    }

    protected function getCoverUrl(string $platformId, int $time): string
    {
        $heroCapsuleUrl = str_replace(['{PLATFORM_ID}', '{NOW}'], [$platformId, $time], self::HERO_CAPSULE_URL);

        $ch = curl_init($heroCapsuleUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        if (curl_errno($ch) || curl_getinfo($ch, CURLINFO_HTTP_CODE) == 404) {
            $heroCapsuleUrl = parent::getCoverUrl($platformId, $time);
        }
        curl_close($ch);

        return $heroCapsuleUrl;
    }

    public function getPlatform(): Platform
    {
        return Platform::steamGame;
    }
}
