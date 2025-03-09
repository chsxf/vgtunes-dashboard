<?php

namespace PlatformHelpers;

use AutomatedActions\SteamProductType;
use Platform;

class SteamGamePlatformHelper extends AbstractSteamPlatformHelper
{
    protected function sqlTypeClause(): string
    {
        return sprintf("IN ('%s','%s')", SteamProductType::game->value, SteamProductType::dlc->value);
    }

    public function getPlatform(): Platform
    {
        return Platform::steamGame;
    }
}
