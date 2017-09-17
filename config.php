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
    'enabled' => true,

    // Maximim number of users on site at any time
    // Set value to -1 to force everyone into the queue
    'threshold' => 1,

    // Period of time a user can be idle in queue before being
    // kicked out (in seconds)
    'timer' => 1,

    // Google anayltics tracking code
    'ga_code' => '',

    // These settings should not need to be adjusted
    'db_name' => 'queue.sqlite',
    'table_name' => 'queue',
    'path' => realpath(dirname(__FILE__))
];