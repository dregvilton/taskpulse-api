<?php

declare(strict_types=1);

namespace app\services;

use app\extensions\DbConnectTrait;
use app\modules\task\repositories\TaskEventRepository;
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
     * @return bool
     * @throws Throwable
     */
    public function consumeOnce(): bool
    {
        $message = $this->broker->getMessage();
        if ($message === null) {
            return false;
        }

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
            $this->broker->acknowledge($message);
        } catch (Throwable $exception) {
            Yii::error($exception, __METHOD__);
            $destination = $attempt < self::MAX_RETRIES ? 'retry' : 'dead';
            $this->broker->publish($body, $eventId, $attempt + 1, $destination);
            $this->broker->acknowledge($message);
        }

        return true;
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
