<?php

declare(strict_types=1);

namespace app\modules\task\repositories;

use app\extensions\DbConnectTrait;
use app\models\Task;
use yii\base\InvalidConfigException;
use yii\db\Exception;
use yii\db\Expression;

/**
 * Хранение событий задач.
 */
final class TaskEventRepository
{
    use DbConnectTrait;

    /**
     * @param Task $task
     * @param string $type
     * @return void
     * @throws InvalidConfigException|Exception
     */
    public function append(Task $task, string $type): void
    {
        $payload = [
            'taskId' => (int) $task->id,
            'authorId' => (int) $task->getAttribute('author_id'),
            'completed' => (bool) $task->completed,
        ];

        $this->getDbConnection()->createCommand()->insert('task_events', [
            'task_id' => $task->id,
            'event_type' => $type,
            'payload' => $payload,
        ])->execute();
    }

    /**
     * @param int $limit
     * @return list<array<string, mixed>>
     * @throws InvalidConfigException|Exception
     */
    public function getPendingForUpdate(int $limit): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->getDbConnection()
            ->createCommand(__DIR__ . '/sqls/get_pending_events.sql')
            ->bindValue(':limit', $limit)
            ->queryAll();

        return $rows;
    }

    /**
     * @param int $eventId
     * @return void
     * @throws InvalidConfigException|Exception
     */
    public function markPublished(int $eventId): void
    {
        $this->getDbConnection()->createCommand()
            ->update('task_events', ['published_at' => new Expression('CURRENT_TIMESTAMP')], ['id' => $eventId])
            ->execute();
    }

    /**
     * @param int $eventId
     * @return bool
     * @throws InvalidConfigException|Exception
     */
    public function claimProcessing(int $eventId): bool
    {
        return $this->getDbConnection()
            ->createCommand(__DIR__ . '/sqls/claim_processing.sql')
            ->bindValue(':eventId', $eventId)
            ->execute() === 1;
    }
}
