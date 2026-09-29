<?php

declare(strict_types=1);

namespace tests\integration;

use app\modules\task\repositories\TaskEventRepository;
use app\services\AnalyticsCache;
use app\services\OutboxPublisher;
use app\services\TaskEventBroker;
use app\services\TaskEventConsumer;
use JsonException;
use PhpAmqpLib\Message\AMQPMessage;
use tests\api\ApiTestCase;
use Yii;
use yii\redis\Connection;

final class TaskEventIntegrationTest extends ApiTestCase
{
    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->db->createCommand()->insert('users', ['full_name' => 'Иван Петров'])->execute();
        [, , $broker] = $this->services();
        $channel = $broker->channel();
        $channel->queue_purge('task.events.main');
        $channel->queue_purge('task.events.retry');
        $channel->queue_purge('task.events.dlq');
    }

    /**
     * @return void
     * @throws JsonException
     */
    public function testPublishAndDeduplicate(): void
    {
        $created = $this->request('POST', '/tasks', ['authorId' => 1, 'title' => 'Событие']);
        self::assertSame(201, $created['status']);

        [$publisher, $consumer, $broker] = $this->services();
        self::assertSame(1, $publisher->publish());
        self::assertSame(0, $publisher->publish());
        self::assertTrue($consumer->consumeOnce());
        self::assertSame(1, (int) $this->db->createCommand('SELECT COUNT(*) FROM processed_task_events')->queryScalar());

        /** @var Connection $redis */
        $redis = Yii::$app->get('redis');
        $before = $redis->executeCommand('GET', ['taskpulse:analytics:tasks:generation']);
        $broker->publish('{"id":1,"type":"created","payload":{"taskId":1}}', 1);
        self::assertTrue($consumer->consumeOnce());
        self::assertSame($before, $redis->executeCommand('GET', ['taskpulse:analytics:tasks:generation']));
        self::assertSame(1, (int) $this->db->createCommand('SELECT COUNT(*) FROM processed_task_events')->queryScalar());
    }

    /**
     * @return void
     * @throws JsonException
     */
    public function testAllMutationsCreateOutboxEvents(): void
    {
        $headers = ['Idempotency-Key' => 'event-test-key'];
        $body = ['authorId' => 1, 'title' => 'Задача'];
        self::assertSame(201, $this->request('POST', '/tasks', $body, $headers)['status']);
        self::assertSame(201, $this->request('POST', '/tasks', $body, $headers)['status']);
        self::assertSame(200, $this->request('PATCH', '/tasks/1', ['completed' => true])['status']);
        self::assertSame(200, $this->request('PATCH', '/tasks/1', ['title' => 'Обновлено'])['status']);
        self::assertSame(204, $this->request('DELETE', '/tasks/1')['status']);

        $types = $this->db->createCommand('SELECT event_type FROM task_events ORDER BY id')->queryColumn();
        self::assertSame(['created', 'updated', 'updated', 'deleted'], $types);
    }

    /**
     * @return void
     * @throws JsonException
     */
    public function testOutboxFailureRollsBackTask(): void
    {
        $this->db->createCommand(
            "ALTER TABLE task_events ADD CONSTRAINT \"chk-test-reject-created\" CHECK (event_type <> 'created')",
        )->execute();

        try {
            $response = $this->request('POST', '/tasks', ['authorId' => 1, 'title' => 'Не сохранится']);
            self::assertSame(500, $response['status']);
            self::assertSame(0, (int) $this->db->createCommand('SELECT COUNT(*) FROM tasks')->queryScalar());
            self::assertSame(0, (int) $this->db->createCommand('SELECT COUNT(*) FROM task_events')->queryScalar());
        } finally {
            $this->db->createCommand('ALTER TABLE task_events DROP CONSTRAINT "chk-test-reject-created"')->execute();
        }
    }

    /**
     * @return void
     * @throws JsonException
     */
    public function testFailedMessageReachesDeadLetterQueue(): void
    {
        self::assertSame(201, $this->request('POST', '/tasks', ['authorId' => 1, 'title' => 'Задача'])['status']);
        [, $consumer, $broker] = $this->services();
        $broker->publish('{broken', 1);

        for ($attempt = 0; $attempt < 4; $attempt++) {
            $deadline = microtime(true) + 8;
            do {
                $consumed = $consumer->consumeOnce();
                if (!$consumed) {
                    usleep(200000);
                }
            } while (!$consumed && microtime(true) < $deadline);
            self::assertTrue($consumed);
        }

        $deadMessage = $broker->channel()->basic_get('task.events.dlq', false);
        self::assertInstanceOf(AMQPMessage::class, $deadMessage);
        self::assertSame('{broken', $deadMessage->getBody());
        $broker->channel()->basic_ack($deadMessage->getDeliveryTag());
        self::assertSame(0, (int) $this->db->createCommand('SELECT COUNT(*) FROM processed_task_events')->queryScalar());
    }

    /**
     * @return array{OutboxPublisher, TaskEventConsumer, TaskEventBroker}
     */
    private function services(): array
    {
        $repository = new TaskEventRepository();
        $broker = new TaskEventBroker(
            $_ENV['RABBITMQ_HOST'] ?? 'rabbitmq',
            (int) ($_ENV['RABBITMQ_PORT'] ?? 5672),
            $_ENV['RABBITMQ_USER'] ?? 'taskpulse',
            $_ENV['RABBITMQ_PASSWORD'] ?? 'taskpulse',
        );
        /** @var Connection $redis */
        $redis = Yii::$app->get('redis');
        $cache = new AnalyticsCache($redis);

        return [
            new OutboxPublisher($repository, $broker),
            new TaskEventConsumer($repository, $broker, $cache),
            $broker,
        ];
    }
}
