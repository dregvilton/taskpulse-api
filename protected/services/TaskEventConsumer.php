<?php

declare(strict_types=1);

namespace app\services;

use app\extensions\DbConnectTrait;
use app\modules\task\repositories\TaskEventRepository;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use RuntimeException;
use Throwable;
use Yii;

/**
 * Обработка событий задач.
 */
final class TaskEventConsumer
{
    use DbConnectTrait;

    private const int MAX_RETRIES = 3;

    /**
     * @param TaskEventRepository $repository
     * @param TaskEventBroker $broker
     * @param AnalyticsCache $analyticsCache
     */
    public function __construct(
        private readonly TaskEventRepository $repository,
        private readonly TaskEventBroker $broker,
        private readonly AnalyticsCache $analyticsCache,
    ) {}

    /**
     * @return void
     * @throws Throwable
     */
    public function run(): void
    {
        $this->broker->consume($this->handleMessage(...));
    }

    /**
     * @param AMQPMessage $message
     * @return void
     * @throws Throwable
     */
    public function handleMessage(AMQPMessage $message): void
    {
        $body = $message->getBody();
        $eventId = (int) $message->get('message_id');
        $headers = $message->get('application_headers');
        $attempt = $headers instanceof AMQPTable
            ? (int) ($headers->getNativeData()['retry-count'] ?? 0)
            : 0;

        try {
            $event = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
            if (
                !is_array($event)
                || $eventId < 1
                || ($event['id'] ?? null) !== $eventId
                || !in_array($event['type'] ?? null, ['created', 'updated', 'deleted'], true)
                || !is_array($event['payload'] ?? null)
                || !is_int($event['payload']['taskId'] ?? null)
            ) {
                throw new RuntimeException('Некорректное событие задачи.');
            }

            $this->process($eventId);
            $message->ack();
            Yii::info(['event' => 'task_event_processed', 'eventId' => $eventId], __METHOD__);
        } catch (Throwable $exception) {
            Yii::error($exception, __METHOD__);
            $destination = $attempt < self::MAX_RETRIES ? 'retry' : 'dead';
            $this->broker->publish($body, $eventId, $attempt + 1, $destination);
            $message->ack();
            Yii::warning([
                'event' => 'task_event_requeued',
                'eventId' => $eventId,
                'attempt' => $attempt + 1,
                'destination' => $destination,
            ], __METHOD__);
        }
    }

    /**
     * @param int $eventId
     * @return void
     * @throws Throwable
     */
    private function process(int $eventId): void
    {
        $transaction = $this->getDbConnection()->beginTransaction();

        try {
            if ($this->repository->claimProcessing($eventId)) {
                $this->analyticsCache->invalidateOrFail();
            }

            $transaction->commit();
        } catch (Throwable $exception) {
            if ($transaction->isActive) {
                $transaction->rollBack();
            }

            throw $exception;
        }
    }
}
