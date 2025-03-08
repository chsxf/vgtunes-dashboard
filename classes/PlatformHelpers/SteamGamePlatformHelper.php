<?php

namespace PlatformHelpers;

use AutomatedActions\SteamProductType;
use Platform;

class SteamGamePlatformHelper extends AbstractSteamPlatformHelper
{
    protected function databaseCategory(): string
    {
        return SteamProductType::game->value;
    }

    public function getPlatform(): Platform
    {
        return Platform::steamGame;
    }
}
