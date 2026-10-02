<?php

declare(strict_types=1);

use app\extensions\DbConnection;
use yii\base\Event;

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
    'on afterOpen' => static function (Event $event): void {
        /** @var DbConnection $db */
        $db = $event->sender;
        $db->createCommand("SET TIME ZONE 'UTC'")->execute();
    },
    'enableSchemaCache' => !YII_DEBUG,
    'schemaCacheDuration' => 3600,
    'schemaCache' => 'cache',
];
