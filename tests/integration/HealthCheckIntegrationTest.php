<?php

declare(strict_types=1);

namespace tests\integration;

use app\extensions\DbConnection;
use app\services\HealthCheck\HealthCheckService;
use app\services\TaskEventBroker;
use app\services\TaskEventTopology;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Yii;
use yii\redis\Connection;

final class HealthCheckIntegrationTest extends TestCase
{
    public function testAllServicesAreAvailable(): void
    {
        self::assertSame(
            [
                'status' => 'ok',
                'services' => [
                    'app' => 'ok',
                    'postgres' => 'ok',
                    'redis' => 'ok',
                    'rabbitmq' => 'ok',
                ],
            ],
            $this->service()->check(),
        );
    }

    public function testUnavailablePostgresDoesNotHideOtherStatuses(): void
    {
        $db = $this->createMock(DbConnection::class);
        $db->expects(self::once())
            ->method('createCommand')
            ->willThrowException(new RuntimeException('База данных недоступна.'));

        $health = $this->service(db: $db)->check();

        self::assertSame('error', $health['status']);
        self::assertSame('error', $health['services']['postgres']);
        self::assertSame('ok', $health['services']['redis']);
        self::assertSame('ok', $health['services']['rabbitmq']);
    }

    public function testUnavailableRedisDoesNotHideOtherStatuses(): void
    {
        $redis = $this->createMock(Connection::class);
        $redis->expects(self::once())
            ->method('executeCommand')
            ->willThrowException(new RuntimeException('Redis недоступен.'));

        $health = $this->service(redis: $redis)->check();

        self::assertSame('error', $health['status']);
        self::assertSame('ok', $health['services']['postgres']);
        self::assertSame('error', $health['services']['redis']);
        self::assertSame('ok', $health['services']['rabbitmq']);
    }

    public function testUnavailableRabbitMqDoesNotHideOtherStatuses(): void
    {
        $broker = new TaskEventBroker(new TaskEventTopology(), '127.0.0.1', 1, 'unused', 'unused');

        $health = $this->service(broker: $broker)->check();

        self::assertSame('error', $health['status']);
        self::assertSame('ok', $health['services']['postgres']);
        self::assertSame('ok', $health['services']['redis']);
        self::assertSame('error', $health['services']['rabbitmq']);
    }

    private function service(
        ?DbConnection $db = null,
        ?Connection $redis = null,
        ?TaskEventBroker $broker = null,
    ): HealthCheckService {
        /** @var DbConnection $database */
        $database = $db ?? Yii::$app->get('db');
        /** @var Connection $redisConnection */
        $redisConnection = $redis ?? Yii::$app->get('redis');
        /** @var TaskEventBroker $rabbitMq */
        $rabbitMq = $broker ?? Yii::$app->get('taskEventBroker');

        return new HealthCheckService($database, $redisConnection, $rabbitMq);
    }
}
