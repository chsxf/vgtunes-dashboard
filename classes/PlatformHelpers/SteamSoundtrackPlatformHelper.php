<?php

namespace PlatformHelpers;

use AutomatedActions\SteamProductType;
use Platform;

class SteamSoundtrackPlatformHelper extends AbstractSteamPlatformHelper
{
    protected function sqlTypeClause(): string
    {
        return sprintf("= '%s'", SteamProductType::other->value);
    }

    public function getPlatform(): Platform
    {
        return Platform::steamSoundtrack;
    }
}
