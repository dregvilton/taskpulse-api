<?php

declare(strict_types=1);

namespace app\services\HealthCheck;

use app\extensions\DbConnection;
use Throwable;

final readonly class HealthCheckService
{
    public function __construct(private DbConnection $db) {}

    /**
     * @return array{status: 'ok'|'error', services: array{app: 'ok', postgres: 'ok'|'error'}}
     */
    public function check(): array
    {
        $services = [
            'app' => 'ok',
            'postgres' => $this->checkPostgres() ? 'ok' : 'error',
        ];

        return [
            'status' => in_array('error', $services, true) ? 'error' : 'ok',
            'services' => $services,
        ];
    }

    /**
     * Проверка PG
     *
     * @return bool
     */
    private function checkPostgres(): bool
    {
        try {
            return (int) $this->db
                ->createCommand(__DIR__ . '/sqls/check_postgres.sql')
                ->queryScalar() === 1;
        } catch (Throwable) {
            return false;
        }
    }
}
