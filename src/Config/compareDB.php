<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Source Database Connection
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your source connection for all database work. Of course
    | you may use many connections at once using the Database library.
    |
    */
    'source' => [
        'driver'         => 'mysql',
        'url'            => env('SOURCE_DATABASE_URL'),
        'host'           => env('SOURCE_DB_HOST', env('DB_HOST', '127.0.0.1')),
        'port'           => env('SOURCE_DB_PORT', env('DB_PORT', '3306')),
        'database'       => env('SOURCE_DB_DATABASE', env('DB_DATABASE', 'source')),
        'username'       => env('SOURCE_DB_USERNAME', env('DB_USERNAME', 'root')),
        'password'       => env('SOURCE_DB_PASSWORD', env('DB_PASSWORD', '')),
        'unix_socket'    => env('SOURCE_DB_SOCKET', env('DB_SOCKET', '')),
        'charset'        => 'utf8mb4',
        'collation'      => 'utf8mb4_unicode_ci',
        'prefix'         => '',
        'prefix_indexes' => true,
        'strict'         => true,
        'engine'         => null,
        'options'        => extension_loaded('pdo_mysql')
            ? array_filter([PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA')])
            : [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Destination Database Connection
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your destination connection for all database work. Of course
    | you may use many connections at once using the Database library.
    |
    */
    'destination' => [
        'driver'         => 'mysql',
        'url'            => env('DESTINATION_DATABASE_URL'),
        'host'           => env('DESTINATION_DB_HOST', '127.0.0.1'),
        'port'           => env('DESTINATION_DB_PORT', '3306'),
        'database'       => env('DESTINATION_DB_DATABASE', 'destination'),
        'username'       => env('DESTINATION_DB_USERNAME', 'root'),
        'password'       => env('DESTINATION_DB_PASSWORD', ''),
        'unix_socket'    => env('DESTINATION_DB_SOCKET', ''),
        'charset'        => 'utf8mb4',
        'collation'      => 'utf8mb4_unicode_ci',
        'prefix'         => '',
        'prefix_indexes' => true,
        'strict'         => true,
        'engine'         => null,
        'options'        => extension_loaded('pdo_mysql')
            ? array_filter([PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA')])
            : [],
    ],
];
