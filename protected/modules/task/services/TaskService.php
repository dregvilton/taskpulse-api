<?php

declare(strict_types=1);

namespace app\modules\task\services;

use app\extensions\DbConnectTrait;
use app\models\Task;
use app\models\User;
use app\modules\task\exceptions\TaskNotFoundException;
use app\modules\task\exceptions\TaskSaveException;
use app\modules\task\forms\TaskForm;
use app\modules\task\forms\TaskSearchForm;
use app\modules\task\repositories\TaskRepository;
use app\modules\user\exceptions\UserNotFoundException;
use Throwable;
use yii\data\SqlDataProvider;
use yii\db\Expression;

/**
 * Сервис управления задачами.
 */
final class TaskService
{
    use DbConnectTrait;

    /**
     * @param TaskRepository $repository
     */
    public function __construct(private readonly TaskRepository $repository) {}

    /**
     * Создать задачу.
     *
     * @param TaskForm $form
     * @return Task
     * @throws TaskSaveException
     */
    public function create(TaskForm $form): Task
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
