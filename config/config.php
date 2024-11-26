<?php

declare(strict_types=1);

use chsxf\MFX\Config;

$secrets = require_once('secrets.php');

return new Config([
    'twig' => [
        'templates' => [
            '../views'
        ]
    ],

    'request' => [
        'default_route' => 'Home/signIn'
    ],

    'response' => [
        'full_errors' => $secrets['FULL_ERRORS'] ?? false
    ],

    'database' => [
        'error_logging' => $secrets['DB_ERROR_LOGGING'] ?? false,
        'servers' => [
            '__default' => [
                'dsn' => "mysql:dbname={$secrets['DB_NAME']};host={$secrets['DB_HOST']}",
                'username' => $secrets['DB_USER'],
                'password' => $secrets['DB_PASS']
            ],
            '__mfx' => '__default'
        ],
        'updaters' => [
            'domain' => 'app',
            'classes' => [
                AppDatabaseUpdater::class
            ]
        ]
    ]
]);
