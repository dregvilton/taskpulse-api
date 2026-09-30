<?php

declare(strict_types=1);

use app\components\JsonLogTarget;
use app\components\SentryLogTarget;
use app\modules\task\repositories\TaskEventRepository;
use app\services\AnalyticsCache;
use app\services\OutboxPublisher;
use app\services\TaskEventBroker;
use app\services\TaskEventConsumer;
use yii\base\InvalidConfigException;
use yii\caching\FileCache;
use yii\redis\Connection as RedisConnection;

$db = require __DIR__ . '/db.php';
$i18n = require __DIR__ . '/i18n.php';
$params = require __DIR__ . '/params.php';
$redis = require __DIR__ . '/redis.php';
$rabbitMq = require __DIR__ . '/rabbitmq.php';

return [
    'id' => 'taskpulse-console',
    'name' => $params['appName'],
    'basePath' => dirname(__DIR__),
    'runtimePath' => dirname(__DIR__) . '/runtime',
    'controllerNamespace' => 'app\\commands',
    'bootstrap' => ['log'],
    'language' => 'ru-RU',
    'sourceLanguage' => 'en-US',
    'components' => [
        'analyticsCache' => static function (): AnalyticsCache {
            $redis = Yii::$app->get('redis', false);
            if (!$redis instanceof RedisConnection) {
                throw new InvalidConfigException('Компонент Redis не настроен.');
            }

            return new AnalyticsCache($redis);
        },
        'cache' => [
            'class' => FileCache::class,
        ],
        'db' => $db,
        'i18n' => $i18n,
        'log' => [
            'flushInterval' => 1,
            'targets' => [
                [
                    'class' => JsonLogTarget::class,
                    'levels' => ['error'],
                    'exportInterval' => 1,
                    'logVars' => [],
                ],
                [
                    'class' => JsonLogTarget::class,
                    'levels' => ['warning'],
                    'categories' => ['app\\*'],
                    'exportInterval' => 1,
                    'logVars' => [],
                ],
                [
                    'class' => JsonLogTarget::class,
                    'levels' => ['info'],
                    'categories' => ['app\\*'],
                    'exportInterval' => 1,
                    'logVars' => [],
                ],
                [
                    'class' => SentryLogTarget::class,
                    'enabled' => YII_ENV_PROD && !empty($_ENV['SENTRY_DSN']),
                    'dsn' => $_ENV['SENTRY_DSN'] ?? '',
                    'environment' => $_ENV['APP_ENV'] ?? 'prod',
                    'levels' => ['error'],
                    'exportInterval' => 1,
                    'logVars' => [],
                ],
            ],
        ],
        'redis' => $redis,
        'taskEventBroker' => $rabbitMq,
        'outboxPublisher' => static function (): OutboxPublisher {
            /** @var TaskEventBroker $broker */
            $broker = Yii::$app->get('taskEventBroker');

            return new OutboxPublisher(new TaskEventRepository(), $broker);
        },
        'taskEventConsumer' => static function (): TaskEventConsumer {
            /** @var TaskEventBroker $broker */
            $broker = Yii::$app->get('taskEventBroker');
            /** @var AnalyticsCache $analyticsCache */
            $analyticsCache = Yii::$app->get('analyticsCache');

            return new TaskEventConsumer(new TaskEventRepository(), $broker, $analyticsCache);
        },
    ],
    'controllerMap' => [
        'migrate' => [
            'class' => yii\console\controllers\MigrateController::class,
            'migrationPath' => '@app/migrations',
        ],
    ],
    'params' => $params,
];
