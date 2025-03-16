<?php

namespace PlatformHelpers;

use AutomatedActions\SteamProductType;
use Platform;

class SteamSoundtrackPlatformHelper extends AbstractSteamPlatformHelper
{
    protected function sqlTypeClause(): string
    {
        return sprintf("IN ('%s','%s')", SteamProductType::other->value, SteamProductType::dlc->value);
    }

    public function getPlatform(): Platform
    {
        return Platform::steamSoundtrack;
    }
}
