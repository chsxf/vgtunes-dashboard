<?php

use chsxf\MFX\Config;

return new Config([
    'twig' => [
        'templates' => [
            '../views'
        ]
    ],

    'request' => [
        'default_route' => 'hello/world'
    ]
]);
