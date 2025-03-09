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

    'scripts' => [
        'bootstrap/js/bootstrap.bundle.min.js'
    ],

    'stylesheets' => [
        'bootstrap/css/bootstrap.min.css',
        'bootstrap/font/bootstrap-icons.min.css',
        'css/style.css'
    ],

    'request' => [
        'default_route' => 'Home/signIn',
        'pre_route_callback' => [GlobalCallbacks::class, 'main']
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
    ],

    'frontend' => [
        'base_url' => $secrets['FRONTEND_BASE_URL']
    ],

    'spotify' => [
        'client_id' => $secrets['SPOTIFY_CLIENT_ID'],
        'client_secret' => $secrets['SPOTIFY_CLIENT_SECRET']
    ],

    'apple_music' => [
        'team_id' => $secrets['APPLE_TEAM_ID'],
        'key_id' => $secrets['APPLE_MUSIC_KEY_ID'],
        'key_path' => __DIR__ . "/AppleMusic_AuthKey_{$secrets['APPLE_MUSIC_KEY_ID']}.p8"
    ],

    'steam' => [
        'api_key' => $secrets['STEAM_API_KEY']
    ],

    'covers' => [
        'output_path' => $secrets['COVERS_PATH'],
        'base_url' => $secrets['COVERS_BASE_URL']
    ],

    'generator' => [
        'key' => $secrets['GENERATOR_KEY']
    ],

    'analytics' => [
        'enabled' => true,
        'endpoint' => $secrets['ANALYTICS_ENDPOINT'],
        'access_key' => $secrets['ANALYTICS_ACCESS_KEY'],
        'domain' => $secrets['ANALYTICS_DOMAIN']
    ],

    'automation' => [
        'allow_debug_actions' => $secrets['AUTOMATION_DEBUG_ACTIONS'] ?? false
    ]
]);
