<?php

declare(strict_types=1);

namespace AutomatedActions;

use Platform;

class BandcampDatabaseUpdater extends AbstractPlatformDatabaseUpdater
{
    protected function getPlatform(): Platform
    {
        return Platform::bandcamp;
    }
}
