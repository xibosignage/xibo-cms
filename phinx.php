<?php
/**
 * Return the PHINX configuration object
 * TODO: we need the traditional installer to create this somehow
 */

return [
    'paths' => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/db/migrations'
    ],
    'environments' => [
        'default_database' => 'production',
        'production' => [
            'adapter' => 'mysql',
            'charset' => 'utf8',
            'host' => $_SERVER['MYSQL_HOST'],
            'post' => $_SERVER['MYSQL_PORT'],
            'name' => $_SERVER['MYSQL_DATABASE'],
            'user' => $_SERVER['MYSQL_USER'],
            'pass' => $_SERVER['MYSQL_PASSWORD'],
        ]
    ]
];