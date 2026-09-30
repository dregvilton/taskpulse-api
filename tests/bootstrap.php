<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/protected/config/bootstrap.php';
require dirname(__DIR__) . '/vendor/yiisoft/yii2/Yii.php';

if (($_ENV['APP_ENV'] ?? '') !== 'test' || !str_ends_with($_ENV['DB_NAME'] ?? '', '_test')) {
    throw new RuntimeException('Тесты разрешено запускать только на отдельной тестовой базе.');
}

$db = require dirname(__DIR__) . '/protected/config/db.php';
$i18n = require dirname(__DIR__) . '/protected/config/i18n.php';
$redis = require dirname(__DIR__) . '/protected/config/redis.php';
$rabbitMq = require dirname(__DIR__) . '/protected/config/rabbitmq.php';

new yii\console\Application([
    'id' => 'taskpulse-tests',
    'basePath' => dirname(__DIR__) . '/protected',
    'language' => 'ru-RU',
    'sourceLanguage' => 'en-US',
    'components' => [
        'db' => $db,
        'i18n' => $i18n,
        'redis' => $redis,
        'taskEventBroker' => $rabbitMq,
    ],
]);
