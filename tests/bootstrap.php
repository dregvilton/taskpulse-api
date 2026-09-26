<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/protected/config/bootstrap.php';
require dirname(__DIR__) . '/vendor/yiisoft/yii2/Yii.php';

$db = require dirname(__DIR__) . '/protected/config/db.php';
$i18n = require dirname(__DIR__) . '/protected/config/i18n.php';
$redis = require dirname(__DIR__) . '/protected/config/redis.php';

new yii\console\Application([
    'id' => 'taskpulse-tests',
    'basePath' => dirname(__DIR__) . '/protected',
    'language' => 'ru-RU',
    'sourceLanguage' => 'en-US',
    'components' => [
        'db' => $db,
        'i18n' => $i18n,
        'redis' => $redis,
    ],
]);
