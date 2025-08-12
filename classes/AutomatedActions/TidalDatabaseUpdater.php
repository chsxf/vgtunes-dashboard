<?php

namespace AutomatedActions;

use Platform;

class TidalDatabaseUpdater extends AbstractPlatformDatabaseUpdater
{
    protected function getPlatform(): Platform
    {
        return Platform::tidal;
    }
}
