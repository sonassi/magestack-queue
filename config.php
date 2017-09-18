<?php

return [
    // Perl regular expression format is used for pattern matching
    'whitelist' => [
        'ip' => [
            '172.16.0..+'
        ],
        'uri' => [
            'paypal',
            'api/v2_soap',
            'sgps',
            'admin(_[a-z0-9]+)?'
        ],
    ],

    // Whether the queue system is enabled or not
    'enabled' => false,

    // Maximim number of users on site at any time
    // Set value to -1 to force everyone into the queue
    'threshold' => 100,

    // Period of time a user can be idle in queue before being
    // kicked out (in seconds)
    'timer' => 600,

    // Google anayltics tracking code
    'ga_code' => '',

    // Database backend
    // sqlite: For single-server/low volume deployments
    // mysql:  For multi-server/high volume deployments
    'database' => [
        'driver' => 'sqlite',
        'name' => 'queue',
        'user' => '',
        'password' => '',
        'host' => '',
        'queue_table' => 'queue',
    ],

    'path' => realpath(dirname(__FILE__))
];