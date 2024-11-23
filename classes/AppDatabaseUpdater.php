<?php

use chsxf\MFX\IDatabaseUpdater;

final class AppDatabaseUpdater implements IDatabaseUpdater
{
    public function key(): string
    {
        return 'app';
    }

    /**
     * Retrieves the path to the SQL update file for this updater
     * @since 1.0
     * @return string
     */
    public function pathToSQLFile(): string
    {
        return '../sql/app.sql';
    }
}
