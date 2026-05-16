<?php

return [
    'info' => [
        'name' => 'SU CMS',
        'version' => '1.0.0',
        'core_version' => '1.0.0',
        'copyright' => 'SU Software',
        'author' => 'SU Software',
    ],
    'page_settings' => [
        'default_title' => 'My SU CMS Application',
        'default_description' => 'This is a sample application using SU CMS.',
        'default_keywords' => 'cms, su cms, php, framework',
        '404' => "404.html",
    ],
    'app' => [
        'base_url' => getenv('APP_BASE_URL') ?: '/',
        'domain' => getenv('APP_DOMAIN') ?: 'http://localhost/',
        'timezone' => 'UTC+1',
        'locale' => ['en_US', 'cs_CZ'],
        'default_locale' => 'en_US',
    ],

    'setup' => [
        'install_token' => getenv('APP_INSTALL_TOKEN') ?: '',
    ],

    'logger' => [
        'log_to_file' => false,
        'log_to_console' => true,
        'log_file' => 'app.log',
        'log_file_path' => __DIR__ . '/../storage/logs',
        'min_level' => 'DEBUG',
    ],

    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'username' => 'default_user',
        'password' => 'default_pass',
        'dbname' => 'default_db',
    ],
    'cache' => [
        'enabled' => false,
        'path' => __DIR__ . '/../storage/cache',
        'ttl' => 3600,
    ],
    'modules' => [
        'AdminModule',
        'AuthModule',
    ],
    'module_auto_manage' => false,
];
