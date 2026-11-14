<?php

declare(strict_types=1);

namespace AutomatedActions;

use Platform;

class TidalDatabaseUpdater extends AbstractPlatformDatabaseUpdater
{
    protected function getPlatform(): Platform
    {
        return Platform::tidal;
    }

    public function getCooldown(): int
    {
        return 5_000;
    }
}
