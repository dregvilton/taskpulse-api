<?php

declare(strict_types=1);

use app\extensions\DbConnection;

return [
    'class' => DbConnection::class,
    'dsn' => sprintf(
        'pgsql:host=%s;port=%s;dbname=%s',
        $_ENV['DB_HOST'] ?? 'postgres',
        $_ENV['DB_PORT'] ?? '5432',
        $_ENV['DB_NAME'] ?? 'taskpulse',
    ),
    'username' => $_ENV['DB_USER'] ?? 'taskpulse',
    'password' => $_ENV['DB_PASSWORD'] ?? 'taskpulse',
    'charset' => 'utf8',
    'enableSchemaCache' => !YII_DEBUG,
    'schemaCacheDuration' => 3600,
    'schemaCache' => 'cache',
];
