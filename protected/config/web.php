<?php

declare(strict_types=1);

use app\components\JsonLogTarget;
use app\components\RequestContext;
use app\components\SentryLogTarget;
use app\extensions\DbConnection;
use app\models\User;
use app\modules\analytics\Module as AnalyticsModule;
use app\modules\task\Module as TaskModule;
use app\modules\user\Module as UserModule;
use app\services\AnalyticsCache;
use app\services\AuthService;
use app\services\HealthCheck\HealthCheckService;
use app\services\TaskEventBroker;
use yii\base\InvalidConfigException;
use yii\caching\FileCache;
use yii\redis\Connection as RedisConnection;
use yii\rest\Serializer;
use yii\rest\UrlRule;
use yii\web\JsonParser;
use yii\web\Response;

$db = require __DIR__ . '/db.php';
$i18n = require __DIR__ . '/i18n.php';
$params = require __DIR__ . '/params.php';
$redis = require __DIR__ . '/redis.php';
$rabbitMq = require __DIR__ . '/rabbitmq.php';
$requestContext = new RequestContext();

return [
    'id' => 'taskpulse-api',
    'name' => $params['appName'],
    'basePath' => dirname(__DIR__),
    'runtimePath' => dirname(__DIR__) . '/runtime',
    'controllerNamespace' => 'app\\controllers',
    'bootstrap' => ['log'],
    'on beforeRequest' => static function () use ($requestContext): void {
        $requestContext->start(Yii::$app->request->headers->get('X-Request-Id'));
    },
    'language' => 'ru-RU',
    'sourceLanguage' => 'en-US',
    'container' => [
        'definitions' => [
            Serializer::class => [
                'class' => Serializer::class,
                'collectionEnvelope' => 'items',
            ],
        ],
    ],
    'modules' => [
        'analytics' => [
            'class' => AnalyticsModule::class,
        ],
        'task' => [
            'class' => TaskModule::class,
        ],
        'user' => [
            'class' => UserModule::class,
        ],
    ],
    'components' => [
        'analyticsCache' => static function (): AnalyticsCache {
            $redis = Yii::$app->get('redis', false);
            if (!$redis instanceof RedisConnection) {
                throw new InvalidConfigException('Компонент Redis не настроен.');
            }

            return new AnalyticsCache($redis);
        },
        'authService' => static fn(): AuthService => new AuthService(),
        'cache' => [
            'class' => FileCache::class,
        ],
        'db' => $db,
        'formatter' => [
            'datetimeFormat' => 'php:c',
            'defaultTimeZone' => 'UTC',
            'timeZone' => 'UTC',
        ],
        'healthCheckService' => static function (): HealthCheckService {
            $db = Yii::$app->get('db', false);
            if (!$db instanceof DbConnection) {
                throw new InvalidConfigException('Компонент базы данных не настроен.');
            }

            $redis = Yii::$app->get('redis', false);
            if (!$redis instanceof RedisConnection) {
                throw new InvalidConfigException('Компонент Redis не настроен.');
            }

            $broker = Yii::$app->get('taskEventBroker', false);
            if (!$broker instanceof TaskEventBroker) {
                throw new InvalidConfigException('Компонент RabbitMQ не настроен.');
            }

            return new HealthCheckService($db, $redis, $broker);
        },
        'i18n' => $i18n,
        'log' => [
            'flushInterval' => 1,
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => JsonLogTarget::class,
                    'levels' => ['error'],
                    'except' => ['yii\\web\\HttpException:4*'],
                    'requestContext' => $requestContext,
                    'exportInterval' => 1,
                    'logVars' => [],
                ],
                [
                    'class' => JsonLogTarget::class,
                    'levels' => ['warning'],
                    'categories' => ['app\\*'],
                    'requestContext' => $requestContext,
                    'exportInterval' => 1,
                    'logVars' => [],
                ],
                [
                    'class' => JsonLogTarget::class,
                    'levels' => ['info'],
                    'categories' => ['app\\*'],
                    'requestContext' => $requestContext,
                    'exportInterval' => 1,
                    'logVars' => [],
                ],
                [
                    'class' => SentryLogTarget::class,
                    'enabled' => YII_ENV_PROD && !empty($_ENV['SENTRY_DSN']),
                    'dsn' => $_ENV['SENTRY_DSN'] ?? '',
                    'environment' => $_ENV['APP_ENV'] ?? 'prod',
                    'levels' => ['error'],
                    'except' => ['yii\\web\\HttpException:4*'],
                    'requestContext' => $requestContext,
                    'exportInterval' => 1,
                    'logVars' => [],
                ],
            ],
        ],
        'requestContext' => $requestContext,
        'user' => [
            'identityClass' => User::class,
            'enableSession' => false,
            'enableAutoLogin' => false,
            'loginUrl' => null,
        ],
        'request' => [
            'cookieValidationKey' => $_ENV['APP_COOKIE_VALIDATION_KEY'] ?? '',
            'enableCsrfValidation' => false,
            'parsers' => [
                'application/json' => JsonParser::class,
            ],
        ],
        'redis' => $redis,
        'taskEventBroker' => $rabbitMq,
        'response' => [
            'format' => Response::FORMAT_JSON,
            'charset' => 'UTF-8',
            'on beforeSend' => static function () use ($requestContext): void {
                $requestId = $requestContext->getRequestId();
                if ($requestId !== null) {
                    Yii::$app->response->headers->set('X-Request-Id', $requestId);
                }
            },
        ],
        'urlManager' => [
            'enablePrettyUrl' => true,
            'enableStrictParsing' => true,
            'showScriptName' => false,
            'rules' => [
                'health' => 'health/index',
                'POST auth/login' => 'auth/login',
                'POST auth/logout' => 'auth/logout',
                'GET analytics/tasks' => 'analytics/task/index',
                'GET users/<id:\d+>/tasks' => 'task/task/user',
                [
                    'class' => UrlRule::class,
                    'controller' => [
                        'tasks' => 'task/task',
                    ],
                ],
                [
                    'class' => UrlRule::class,
                    'controller' => [
                        'users' => 'user/user',
                    ],
                ],
            ],
        ],
    ],
    'params' => $params,
];
