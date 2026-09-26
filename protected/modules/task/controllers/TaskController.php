<?php

declare(strict_types=1);

namespace app\modules\task\controllers;

use app\controllers\BaseController;
use app\models\Task;
use app\modules\task\exceptions\TaskNotFoundException;
use app\modules\task\forms\TaskForm;
use app\modules\task\forms\TaskSearchForm;
use app\modules\task\Module;
use app\modules\task\services\TaskService;
use app\modules\user\exceptions\UserNotFoundException;
use Throwable;
use Yii;
use yii\base\InvalidConfigException;
use yii\data\SqlDataProvider;
use yii\helpers\Url;
use yii\web\NotFoundHttpException;
use yii\web\ServerErrorHttpException;

/**
 * Управление задачами.
 */
final class TaskController extends BaseController
{
    /** @var TaskService Сервис задач. */
    private readonly TaskService $taskService;

    /**
     * @param string $id
     * @param Module $module
     * @param array<string, mixed> $config
     * @return void
     * @throws InvalidConfigException
     */
    public function __construct(string $id, Module $module, array $config = [])
    {
        /** @var TaskService $taskService */
        $taskService = $module->get(TaskService::class);
        $this->taskService = $taskService;

        parent::__construct($id, $module, $config);
    }

    /**
     * @inheritDoc
     * @return array<string, list<string>>
     */
    protected function verbs(): array
    {
        return [
            'index' => ['GET'],
            'user' => ['GET'],
            'view' => ['GET'],
            'create' => ['POST'],
            'update' => ['PATCH'],
            'delete' => ['DELETE'],
        ];
    }

    /**
     * Получить список задач.
     *
     * @return SqlDataProvider|TaskSearchForm
     */
    public function actionIndex(): SqlDataProvider|TaskSearchForm
    {
        $form = $this->getSearchForm();
        if (!$form->validate()) {
            return $form;
        }

        return $this->taskService->getList($form);
    }

    /**
     * Получить задачи пользователя.
     *
     * @param int $id
     * @return SqlDataProvider|TaskSearchForm
     * @throws NotFoundHttpException
     * @throws ServerErrorHttpException
     */
    public function actionUser(int $id): SqlDataProvider|TaskSearchForm
    {
        $form = $this->getSearchForm();
        if (!$form->validate()) {
            return $form;
        }

        try {
            return $this->taskService->getUserTasks($id, $form);
        } catch (UserNotFoundException $exception) {
            throw new NotFoundHttpException(
                message: Yii::t('user', 'User not found.'),
                previous: $exception,
            );
        } catch (Throwable $exception) {
            Yii::error($exception, __METHOD__);

            throw new ServerErrorHttpException(
                message: Yii::t('task', 'Failed to get tasks.'),
                previous: $exception,
            );
        }
    }

    /**
     * Получить задачу.
     *
     * @param int $id
     * @return Task
     * @throws NotFoundHttpException
     * @throws ServerErrorHttpException
     */
    public function actionView(int $id): Task
    {
        try {
            return $this->taskService->getById($id);
        } catch (TaskNotFoundException $exception) {
            throw new NotFoundHttpException(
                message: Yii::t('task', 'Task not found.'),
                previous: $exception,
            );
        } catch (Throwable $exception) {
            Yii::error($exception, __METHOD__);

            throw new ServerErrorHttpException(
                message: Yii::t('task', 'Failed to get task.'),
                previous: $exception,
            );
        }
    }

    /**
     * Создать задачу.
     *
     * @return Task|TaskForm
     * @throws ServerErrorHttpException
     */
    public function actionCreate(): Task|TaskForm
    {
        $form = new TaskForm(['scenario' => TaskForm::SCENARIO_CREATE]);
        $form->load($this->request->getBodyParams(), '');

        if (!$form->validate()) {
            return $form;
        }

        try {
            $task = $this->taskService->create($form);
            $this->response->setStatusCode(self::CREATED);
            $this->response->headers->set(
                'Location',
                Url::toRoute(['/task/task/view', 'id' => $task->id]),
            );

            return $task;
        } catch (Throwable $exception) {
            Yii::error($exception, __METHOD__);

            throw new ServerErrorHttpException(
                message: Yii::t('task', 'Failed to create task.'),
                previous: $exception,
            );
        }
    }

    /**
     * Обновить задачу.
     *
     * @param int $id
     * @return Task|TaskForm
     * @throws NotFoundHttpException
     * @throws ServerErrorHttpException
     * @throws Throwable
     */
    public function actionUpdate(int $id): Task|TaskForm
    {
        $form = new TaskForm(['scenario' => TaskForm::SCENARIO_UPDATE]);
        $form->load($this->request->getBodyParams(), '');

        if (!$form->validate()) {
            return $form;
        }

        try {
            return $this->taskService->update($id, $form);
        } catch (TaskNotFoundException $exception) {
            throw new NotFoundHttpException(
                message: Yii::t('task', 'Task not found.'),
                previous: $exception,
            );
        } catch (Throwable $exception) {
            Yii::error($exception, __METHOD__);

            throw new ServerErrorHttpException(
                message: Yii::t('task', 'Failed to update task.'),
                previous: $exception,
            );
        }
    }

    /**
     * Удалить задачу.
     *
     * @param int $id
     * @return void
     * @throws NotFoundHttpException
     * @throws ServerErrorHttpException
     */
    public function actionDelete(int $id): void
    {
        try {
            $this->taskService->delete($id);
            $this->response->setStatusCode(self::NO_CONTENT);
        } catch (TaskNotFoundException $exception) {
            throw new NotFoundHttpException(
                message: Yii::t('task', 'Task not found.'),
                previous: $exception,
            );
        } catch (Throwable $exception) {
            Yii::error($exception, __METHOD__);

            throw new ServerErrorHttpException(
                message: Yii::t('task', 'Failed to delete task.'),
                previous: $exception,
            );
        }
    }

    /**
     * Создать форму фильтрации.
     *
     * @return TaskSearchForm
     */
    private function getSearchForm(): TaskSearchForm
    {
        $form = new TaskSearchForm();
        $form->load($this->request->getQueryParams(), '');

        return $form;
    }
}
