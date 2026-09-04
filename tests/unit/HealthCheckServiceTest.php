<?php

declare(strict_types=1);

namespace tests\unit;

use app\extensions\DbConnection;
use app\services\HealthCheck\HealthCheckService;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use yii\db\Command;

final class HealthCheckServiceTest extends TestCase
{
    public function testCheckReportsHealthyPostgres(): void
    {
        $command = $this->createMock(Command::class);
        $command->expects(self::once())
            ->method('queryScalar')
            ->willReturn('1');

        $db = $this->createMock(DbConnection::class);
        $db->expects(self::once())
            ->method('createCommand')
            ->with(dirname(__DIR__, 2) . '/protected/services/HealthCheck/sqls/check_postgres.sql')
            ->willReturn($command);

        self::assertSame(
            [
                'status' => 'ok',
                'services' => [
                    'app' => 'ok',
                    'postgres' => 'ok',
                ],
            ],
            (new HealthCheckService($db))->check(),
        );
    }

    public function testCheckReportsUnavailablePostgres(): void
    {
        $db = $this->createMock(DbConnection::class);
        $db->expects(self::once())
            ->method('createCommand')
            ->with(dirname(__DIR__, 2) . '/protected/services/HealthCheck/sqls/check_postgres.sql')
            ->willThrowException(new RuntimeException('База данных недоступна.'));

        self::assertSame(
            [
                'status' => 'error',
                'services' => [
                    'app' => 'ok',
                    'postgres' => 'error',
                ],
            ],
            (new HealthCheckService($db))->check(),
        );
    }
}
