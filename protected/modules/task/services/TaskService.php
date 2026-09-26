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
use app\modules\task\repositories\TaskRepository;
use app\modules\user\exceptions\UserNotFoundException;
use app\services\AnalyticsCache;
use JsonException;
use RuntimeException;
use Throwable;
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
     * @param AnalyticsCache $analyticsCache
     * @return void
     */
    public function __construct(
        private readonly TaskRepository $repository,
        private readonly IdempotencyRepository $idempotencyRepository,
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
        $task = $this->createTask($form);
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
     * @param string $key
     * @param string $requestHash
     * @return array{
     *     taskId: int,
     *     body: array<string, mixed>
     * }|null
     * @throws IdempotencyConflictException|JsonException|RuntimeException
     * @throws InvalidConfigException|Exception
     */
    public function findCreation(string $key, string $requestHash): ?array
    {
        $row = $this->idempotencyRepository->getByKey($key);

        return $row === false ? null : $this->restoreCreation($row, $requestHash);
    }

    /**
     * Атомарно сохранить задачу и ответ для ключа идемпотентности.
     *
     * @param TaskForm $form
     * @param string $key
     * @param string $requestHash
     * @return array{
     *     taskId: int,
     *     body: array<string, mixed>
     * }
     * @throws Throwable
     */
    public function createIdempotent(TaskForm $form, string $key, string $requestHash): array
    {
        $transaction = $this->getDbConnection()->beginTransaction();

        try {
            if ($this->idempotencyRepository->claim($key, $requestHash) === false) {
                $result = $this->findCreation($key, $requestHash);
                if ($result === null) {
                    throw new RuntimeException('Не удалось получить сохранённый ответ задачи.');
                }

                $transaction->commit();

                return $result;
            }

            $task = $this->createTask($form);
            $body = $task->toArray();
            $encodedBody = json_encode($body, JSON_THROW_ON_ERROR);
            if ($this->idempotencyRepository->complete($key, (int) $task->id, $encodedBody) !== 1) {
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
     * @return Task
     * @throws TaskNotFoundException
     */
    public function getById(int $id): Task
    {
        return $this->getExistingTask($id);
    }

    /**
     * Получить список задач.
     *
     * @param TaskSearchForm $form
     * @return SqlDataProvider
     */
    public function getList(TaskSearchForm $form): SqlDataProvider
    {
        return $this->repository->getList($form);
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

        return $this->repository->getList($form, $userId);
    }

    /**
     * Обновить задачу.
     *
     * @param int $id
     * @param TaskForm $form
     * @return Task
     * @throws TaskNotFoundException
     * @throws TaskSaveException
     * @throws Throwable
     */
    public function update(int $id, TaskForm $form): Task
    {
        if ($form->hasField('completed')) {
            return $this->updateState($id, $form);
        }

        $task = $this->getExistingTask($id);
        $task->setAttributes($form->getTaskAttributes(), false);

        $this->save($task);
        $this->analyticsCache->invalidate();
        $task->refresh();

        return $task;
    }

    /**
     * Удалить задачу.
     *
     * @param int $id
     * @return void
     * @throws TaskNotFoundException
     * @throws TaskSaveException
     */
    public function delete(int $id): void
    {
        $task = $this->getExistingTask($id);
        $task->setAttribute('deleted_at', new Expression('CURRENT_TIMESTAMP'));

        $this->save($task);
        $this->analyticsCache->invalidate();
    }

    /**
     * Обновить состояние задачи в транзакции.
     *
     * @param int $id
     * @param TaskForm $form
     * @return Task
     * @throws Throwable
     */
    private function updateState(int $id, TaskForm $form): Task
    {
        $transaction = $this->getDbConnection()->beginTransaction();

        try {
            $task = $this->repository->getByIdForUpdate($id);
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
     * Получить существующую задачу.
     *
     * @param int $id
     * @return Task
     * @throws TaskNotFoundException
     */
    private function getExistingTask(int $id): Task
    {
        $task = $this->repository->getById($id);
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
