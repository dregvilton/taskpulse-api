<?php

declare(strict_types=1);

namespace app\modules\task\services;

use app\extensions\DbConnectTrait;
use app\models\Task;
use app\models\User;
use app\modules\task\exceptions\IdempotencyConflictException;
use app\modules\task\exceptions\TaskNotFoundException;
use app\modules\task\exceptions\TaskSaveException;
use app\modules\task\forms\TaskForm;
use app\modules\task\forms\TaskSearchForm;
use app\modules\task\repositories\IdempotencyRepository;
use app\modules\task\repositories\TaskEventRepository;
use app\modules\task\repositories\TaskRepository;
use app\modules\user\exceptions\UserNotFoundException;
use app\services\AnalyticsCache;
use JsonException;
use RuntimeException;
use Throwable;
use Yii;
use yii\base\InvalidConfigException;
use yii\data\SqlDataProvider;
use yii\db\Exception;
use yii\db\Expression;

/**
 * Сервис управления задачами.
 */
final class TaskService
{
    use DbConnectTrait;

    /**
     * @param TaskRepository $repository
     * @param IdempotencyRepository $idempotencyRepository
     * @param TaskEventRepository $eventRepository
     * @param AnalyticsCache $analyticsCache
     * @return void
     */
    public function __construct(
        private readonly TaskRepository $repository,
        private readonly IdempotencyRepository $idempotencyRepository,
        private readonly TaskEventRepository $eventRepository,
        private readonly AnalyticsCache $analyticsCache,
    ) {}

    /**
     * Создать задачу.
     *
     * @param TaskForm $form
     * @return Task
     * @throws TaskSaveException
     */
    public function create(TaskForm $form): Task
    {
        $task = $this->persistWithEvent(fn(): Task => $this->createTask($form), 'created');
        $this->analyticsCache->invalidate();

        return $task;
    }

    /**
     * Получить отпечаток тела запроса до валидации.
     *
     * @param array<string, mixed> $body
     * @return string
     * @throws JsonException
     */
    public function getCreationFingerprint(array $body): string
    {
        return hash('sha256', json_encode($this->sortRequestBody($body), JSON_THROW_ON_ERROR));
    }

    /**
     * Сортировать ключи JSON-объектов, не меняя порядок списков.
     *
     * @param array<array-key, mixed> $body
     * @return array<array-key, mixed>
     */
    private function sortRequestBody(array $body): array
    {
        if (!array_is_list($body)) {
            ksort($body);
        }

        foreach ($body as $key => $value) {
            if (is_array($value)) {
                $body[$key] = $this->sortRequestBody($value);
            }
        }

        return $body;
    }

    /**
     * Найти ответ на уже обработанный запрос.
     *
     * @param int $ownerId
     * @param string $key
     * @param string $requestHash
     * @return array{
     *     taskId: int,
     *     body: array<string, mixed>
     * }|null
     * @throws IdempotencyConflictException|JsonException|RuntimeException
     * @throws InvalidConfigException|Exception
     */
    public function findCreation(int $ownerId, string $key, string $requestHash): ?array
    {
        $row = $this->idempotencyRepository->getByKey($ownerId, $key);

        return $row === false ? null : $this->restoreCreation($row, $requestHash);
    }

    /**
     * Атомарно сохранить задачу и ответ для ключа идемпотентности.
     *
     * @param TaskForm $form
     * @param int $ownerId
     * @param string $key
     * @param string $requestHash
     * @return array{
     *     taskId: int,
     *     body: array<string, mixed>
     * }
     * @throws Throwable
     */
    public function createIdempotent(TaskForm $form, int $ownerId, string $key, string $requestHash): array
    {
        $transaction = $this->getDbConnection()->beginTransaction();

        try {
            if ($this->idempotencyRepository->claim($ownerId, $key, $requestHash) === false) {
                $result = $this->findCreation($ownerId, $key, $requestHash);
                if ($result === null) {
                    throw new RuntimeException('Не удалось получить сохранённый ответ задачи.');
                }

                $transaction->commit();

                return $result;
            }

            $task = $this->createTask($form);
            $this->eventRepository->append($task, 'created');
            $body = $task->toArray();
            $encodedBody = json_encode($body, JSON_THROW_ON_ERROR);
            if ($this->idempotencyRepository->complete($ownerId, $key, (int) $task->id, $encodedBody) !== 1) {
                throw new RuntimeException('Не удалось сохранить ответ задачи.');
            }

            $transaction->commit();
            $this->analyticsCache->invalidate();

            return [
                'taskId' => (int) $task->id,
                'body' => $body,
            ];
        } catch (Throwable $exception) {
            if ($transaction->isActive) {
                $transaction->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * @param TaskForm $form
     * @return Task
     * @throws TaskSaveException
     */
    private function createTask(TaskForm $form): Task
    {
        $task = new Task();
        $task->setAttributes($form->getTaskAttributes(), false);
        $task->setAttribute(
            'completed_at',
            $task->completed ? new Expression('CURRENT_TIMESTAMP') : null,
        );

        $this->save($task);
        $task->refresh();

        return $task;
    }

    /**
     * @param array<string, mixed> $row
     * @param string $requestHash
     * @return array{
     *     taskId: int,
     *     body: array<string, mixed>
     * }
     * @throws IdempotencyConflictException|JsonException|RuntimeException
     */
    private function restoreCreation(array $row, string $requestHash): array
    {
        if (!hash_equals((string) $row['request_hash'], $requestHash)) {
            throw new IdempotencyConflictException('Ключ уже использован для другого запроса.');
        }

        if ($row['task_id'] === null || !is_string($row['response_body'])) {
            throw new RuntimeException('Сохранённый ответ задачи не завершён.');
        }

        $body = json_decode($row['response_body'], true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($body) || array_is_list($body)) {
            throw new RuntimeException('Сохранённый ответ задачи имеет неверный формат.');
        }

        return [
            'taskId' => (int) $row['task_id'],
            'body' => $body,
        ];
    }

    /**
     * Получить задачу.
     *
     * @param int $id
     * @param int $ownerId
     * @return Task
     * @throws TaskNotFoundException
     */
    public function getById(int $id, int $ownerId): Task
    {
        return $this->getExistingTask($id, $ownerId);
    }

    /**
     * Получить список задач.
     *
     * @param TaskSearchForm $form
     * @param int $ownerId
     * @return SqlDataProvider
     */
    public function getList(TaskSearchForm $form, int $ownerId): SqlDataProvider
    {
        return $this->formatListDates($this->repository->getList($form, $ownerId));
    }

    /**
     * Получить задачи пользователя.
     *
     * @param int $userId
     * @param TaskSearchForm $form
     * @return SqlDataProvider
     * @throws UserNotFoundException
     */
    public function getUserTasks(int $userId, TaskSearchForm $form): SqlDataProvider
    {
        if (!User::find()->where(['id' => $userId, 'deleted_at' => null])->exists()) {
            throw new UserNotFoundException("Пользователь {$userId} не найден.");
        }

        return $this->formatListDates($this->repository->getList($form, $userId));
    }

    /**
     * Привести даты списка к формату API.
     *
     * @param SqlDataProvider $provider
     * @return SqlDataProvider
     */
    private function formatListDates(SqlDataProvider $provider): SqlDataProvider
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $provider->getModels();
        $provider->setModels(array_map(static function (array $row): array {
            $row['createdAt'] = Yii::$app->formatter->asDatetime($row['createdAt']);
            $row['updatedAt'] = Yii::$app->formatter->asDatetime($row['updatedAt']);
            if ($row['completedAt'] !== null) {
                $row['completedAt'] = Yii::$app->formatter->asDatetime($row['completedAt']);
            }

            return $row;
        }, $rows));

        return $provider;
    }

    /**
     * Обновить задачу.
     *
     * @param int $id
     * @param TaskForm $form
     * @param int $ownerId
     * @return Task
     * @throws TaskNotFoundException
     * @throws TaskSaveException
     * @throws Throwable
     */
    public function update(int $id, TaskForm $form, int $ownerId): Task
    {
        if ($form->hasField('completed')) {
            return $this->updateState($id, $form, $ownerId);
        }

        $task = $this->persistWithEvent(function () use ($id, $form, $ownerId): Task {
            $task = $this->getExistingTask($id, $ownerId);
            $task->setAttributes($form->getTaskAttributes(), false);
            $this->save($task);

            return $task;
        }, 'updated');
        $this->analyticsCache->invalidate();
        $task->refresh();

        return $task;
    }

    /**
     * Удалить задачу.
     *
     * @param int $id
     * @param int $ownerId
     * @return void
     * @throws TaskNotFoundException
     * @throws TaskSaveException
     */
    public function delete(int $id, int $ownerId): void
    {
        $this->persistWithEvent(function () use ($id, $ownerId): Task {
            $task = $this->getExistingTask($id, $ownerId);
            $task->setAttribute('deleted_at', new Expression('CURRENT_TIMESTAMP'));
            $this->save($task);

            return $task;
        }, 'deleted');
        $this->analyticsCache->invalidate();
    }

    /**
     * Обновить состояние задачи в транзакции.
     *
     * @param int $id
     * @param TaskForm $form
     * @param int $ownerId
     * @return Task
     * @throws Throwable
     */
    private function updateState(int $id, TaskForm $form, int $ownerId): Task
    {
        $transaction = $this->getDbConnection()->beginTransaction();

        try {
            $task = $this->repository->getByIdForUpdate($id, $ownerId);
            if (!$task instanceof Task) {
                throw new TaskNotFoundException("Задача {$id} не найдена.");
            }

            $completed = (bool) $form->completed;
            $stateChanged = $task->completed !== $completed;

            $task->setAttributes($form->getTaskAttributes(), false);
            if ($stateChanged) {
                $task->setAttribute(
                    'completed_at',
                    $completed ? new Expression('CURRENT_TIMESTAMP') : null,
                );
            }

            $this->save($task);
            $this->eventRepository->append($task, 'updated');
            $transaction->commit();
            $this->analyticsCache->invalidate();
            $task->refresh();

            return $task;
        } catch (Throwable $exception) {
            if ($transaction->isActive) {
                $transaction->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * @param callable(): Task $operation
     * @param string $eventType
     * @return Task
     * @throws Throwable
     */
    private function persistWithEvent(callable $operation, string $eventType): Task
    {
        $transaction = $this->getDbConnection()->beginTransaction();

        try {
            $task = $operation();
            $this->eventRepository->append($task, $eventType);
            $transaction->commit();

            return $task;
        } catch (Throwable $exception) {
            if ($transaction->isActive) {
                $transaction->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * Получить существующую задачу.
     *
     * @param int $id
     * @param int $ownerId
     * @return Task
     * @throws TaskNotFoundException
     */
    private function getExistingTask(int $id, int $ownerId): Task
    {
        $task = $this->repository->getById($id, $ownerId);
        if (!$task instanceof Task) {
            throw new TaskNotFoundException("Задача {$id} не найдена.");
        }

        return $task;
    }

    /**
     * Сохранить задачу.
     *
     * @param Task $task
     * @return void
     * @throws TaskSaveException
     */
    private function save(Task $task): void
    {
        if (!$task->save(false)) {
            throw new TaskSaveException('Не удалось сохранить задачу.');
        }
    }
}
