<?php

declare(strict_types=1);

namespace app\controllers;

use app\services\HealthCheck\HealthCheckService;
use Yii;
use yii\base\InvalidConfigException;
use yii\web\ServerErrorHttpException;

final class HealthController extends BaseController
{
    /**
     * @return array<string, list<string>>
     */
    protected function verbs(): array
    {
        return [
            'index' => ['GET'],
        ];
    }

    /**
     * @return array{status: 'ok'|'error', services: array<string, 'ok'|'error'>}
     * @throws ServerErrorHttpException|InvalidConfigException
     */
    public function actionIndex(): array
    {
        $healthCheckService = Yii::$app->get('healthCheckService', false);
        if (!$healthCheckService instanceof HealthCheckService) {
            throw new ServerErrorHttpException('Сервис проверки состояния не настроен.');
        }

        $health = $healthCheckService->check();
        $this->response->setStatusCode(
            $health['status'] === 'ok' ? self::OK : self::SERVICE_UNAVAILABLE,
        );

        return $health;
    }
}
