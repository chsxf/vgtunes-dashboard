<?php

namespace PlatformHelpers;

use AutomatedActions\SteamProductType;
use Platform;

class SteamSoundtrackPlatformHelper extends AbstractSteamPlatformHelper
{
    protected function databaseCategory(): string
    {
        return SteamProductType::other->value;
    }

    public function getPlatform(): Platform
    {
        return Platform::steamSoundtrack;
    }
}
