<?php

declare(strict_types=1);

namespace app\services\HealthCheck;

use app\extensions\DbConnection;
use app\services\TaskEventBroker;
use Throwable;
use yii\redis\Connection;

final readonly class HealthCheckService
{
    /**
     * @param DbConnection $db
     * @param Connection $redis
     * @param TaskEventBroker $broker
     * @return void
     */
    public function __construct(
        private DbConnection $db,
        private Connection $redis,
        private TaskEventBroker $broker,
    ) {}

    /**
     * @return array{status: 'ok'|'error', services: array<string, 'ok'|'error'>}
     */
    public function check(): array
    {
        $services = ['app' => 'ok'];
        $checks = [
            'postgres' => $this->checkPostgres(...),
            'redis' => $this->checkRedis(...),
            'rabbitmq' => $this->checkRabbitMq(...),
        ];
        foreach ($checks as $name => $check) {
            try {
                $services[$name] = $check() ? 'ok' : 'error';
            } catch (Throwable) {
                $services[$name] = 'error';
            }
        }

        return [
            'status' => in_array('error', $services, true) ? 'error' : 'ok',
            'services' => $services,
        ];
    }

    /**
     * Проверка PG.
     *
     * @return bool
     * @throws Throwable
     */
    private function checkPostgres(): bool
    {
        return (int) $this->db
            ->createCommand(__DIR__ . '/sqls/check_postgres.sql')
            ->queryScalar() === 1;
    }

    /**
     * Проверка Redis.
     *
     * @return bool
     * @throws Throwable
     */
    private function checkRedis(): bool
    {
        return $this->redis->executeCommand('PING') === true;
    }

    /**
     * Проверка RabbitMQ.
     *
     * @return bool
     * @throws Throwable
     */
    private function checkRabbitMq(): bool
    {
        $this->broker->checkConnection();

        return true;
    }
}
