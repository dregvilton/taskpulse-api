<?php

declare(strict_types=1);

namespace app\services;

use app\extensions\DbConnectTrait;
use app\modules\task\repositories\TaskEventRepository;
use Throwable;

/**
 * Публикация outbox-событий.
 */
final class OutboxPublisher
{
    use DbConnectTrait;

    public const int DEFAULT_BATCH_SIZE = 100;

    /**
     * @param TaskEventRepository $repository
     * @param TaskEventBroker $broker
     */
    public function __construct(
        private readonly TaskEventRepository $repository,
        private readonly TaskEventBroker $broker,
    ) {}

    /**
     * @param int $limit
     * @return int
     * @throws Throwable
     */
    public function publish(int $limit = self::DEFAULT_BATCH_SIZE): int
    {
        $transaction = $this->getDbConnection()->beginTransaction();

        try {
            $events = $this->repository->getPendingForUpdate($limit);
            foreach ($events as $event) {
                $body = json_encode([
                    'id' => (int) $event['id'],
                    'type' => $event['event_type'],
                    'payload' => json_decode((string) $event['payload'], true, flags: JSON_THROW_ON_ERROR),
                ], JSON_THROW_ON_ERROR);
                $this->broker->publish($body, (int) $event['id']);
                $this->repository->markPublished((int) $event['id']);
            }

            $transaction->commit();

            return count($events);
        } catch (Throwable $exception) {
            if ($transaction->isActive) {
                $transaction->rollBack();
            }

            throw $exception;
        }
    }
}
