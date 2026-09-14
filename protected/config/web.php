<?php

declare(strict_types=1);

use app\extensions\DbConnection;
use app\modules\task\Module as TaskModule;
use app\modules\user\Module as UserModule;
use app\services\HealthCheck\HealthCheckService;
use yii\base\InvalidConfigException;
use yii\caching\FileCache;
use yii\log\FileTarget;
use yii\rest\Serializer;
use yii\rest\UrlRule;
use yii\web\JsonParser;
use yii\web\Response;

$db = require __DIR__ . '/db.php';
$i18n = require __DIR__ . '/i18n.php';
$params = require __DIR__ . '/params.php';

return [
    'id' => 'taskpulse-api',
    'name' => $params['appName'],
    'basePath' => dirname(__DIR__),
    'runtimePath' => dirname(__DIR__) . '/runtime',
    'controllerNamespace' => 'app\\controllers',
    'bootstrap' => ['log'],
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
        'task' => [
            'class' => TaskModule::class,
        ],
        'user' => [
            'class' => UserModule::class,
        ],
    ],
    'components' => [
        'cache' => [
            'class' => FileCache::class,
        ],
        'db' => $db,
        'healthCheckService' => static function (): HealthCheckService {
            $db = Yii::$app->get('db', false);
            if (!$db instanceof DbConnection) {
                throw new InvalidConfigException('Компонент базы данных не настроен.');
            }

            return new HealthCheckService($db);
        },
        'i18n' => $i18n,
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => FileTarget::class,
                    'levels' => ['error', 'warning', 'info'],
                    'logFile' => '@runtime/logs/app.log',
                    'logVars' => [],
                ],
            ],
        ],
        'request' => [
            'cookieValidationKey' => $_ENV['APP_COOKIE_VALIDATION_KEY'] ?? '',
            'enableCsrfValidation' => false,
            'parsers' => [
                'application/json' => JsonParser::class,
            ],
        ],
        'response' => [
            'format' => Response::FORMAT_JSON,
            'charset' => 'UTF-8',
        ],
        'urlManager' => [
            'enablePrettyUrl' => true,
            'enableStrictParsing' => true,
            'showScriptName' => false,
            'rules' => [
                'health' => 'health/index',
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
