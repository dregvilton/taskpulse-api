<?php

declare(strict_types=1);

namespace app\modules\task\controllers;

use app\controllers\BaseController;
use app\models\Task;
use app\modules\task\exceptions\IdempotencyConflictException;
use app\modules\task\exceptions\TaskNotFoundException;
use app\modules\task\forms\TaskForm;
use app\modules\task\forms\TaskSearchForm;
use app\modules\task\Module;
use app\modules\task\services\TaskService;
use app\modules\user\exceptions\UserNotFoundException;
use Yii;
use yii\base\InvalidConfigException;
use yii\data\SqlDataProvider;
use yii\helpers\Url;
use yii\web\ConflictHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

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

        $ownerId = $this->currentUserId();
        if ($form->authorId !== null && (int) $form->authorId !== $ownerId) {
            throw new ForbiddenHttpException('Нет доступа к задачам этого пользователя.');
        }

        return $this->taskService->getList($form, $ownerId);
    }

    /**
     * Получить задачи пользователя.
     *
     * @param int $id
     * @return SqlDataProvider|TaskSearchForm
     * @throws NotFoundHttpException
     */
    public function actionUser(int $id): SqlDataProvider|TaskSearchForm
    {
        if ($id !== $this->currentUserId()) {
            throw new ForbiddenHttpException('Нет доступа к задачам этого пользователя.');
        }

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
        }
    }

    /**
     * Получить задачу.
     *
     * @param int $id
     * @return Task
     * @throws NotFoundHttpException
     */
    public function actionView(int $id): Task
    {
        try {
            return $this->taskService->getById($id, $this->currentUserId());
        } catch (TaskNotFoundException $exception) {
            throw new NotFoundHttpException(
                message: Yii::t('task', 'Task not found.'),
                previous: $exception,
            );
        }
    }

    /**
     * Создать задачу.
     *
     * @return Task|TaskForm|array<string, mixed>
     * @throws ConflictHttpException
     */
    public function actionCreate(): Task|TaskForm|array
    {
        $body = $this->getBodyObject();
        $form = new TaskForm(['scenario' => TaskForm::SCENARIO_CREATE]);
        $form->load($body, '');
        $ownerId = $this->currentUserId();
        $form->authorId = $ownerId;
        $form->idempotencyKey = $this->request->headers->get('Idempotency-Key');

        if (!$form->validate(['idempotencyKey'])) {
            return $form;
        }

        try {
            $key = $form->idempotencyKey;
            $requestHash = $key === null ? null : $this->taskService->getCreationFingerprint($body);
            $result = $key === null ? null : $this->taskService->findCreation($ownerId, $key, $requestHash);

            if ($result === null) {
                if (!$form->validate()) {
                    return $form;
                }

                $result = $key === null
                    ? $this->taskService->create($form)
                    : $this->taskService->createIdempotent($form, $ownerId, $key, $requestHash);
            }

            $taskId = is_array($result) ? $result['taskId'] : $result->id;
            $this->response->setStatusCode(self::CREATED);
            $this->response->headers->set(
                'Location',
                Url::toRoute(['/task/task/view', 'id' => $taskId]),
            );

            return is_array($result) ? $result['body'] : $result;
        } catch (IdempotencyConflictException $exception) {
            throw new ConflictHttpException(
                message: Yii::t('task', 'Idempotency key was already used for another request.'),
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
     */
    public function actionUpdate(int $id): Task|TaskForm
    {
        $form = new TaskForm(['scenario' => TaskForm::SCENARIO_UPDATE]);
        $form->load($this->getBodyObject(), '');

        if (!$form->validate()) {
            return $form;
        }

        try {
            return $this->taskService->update($id, $form, $this->currentUserId());
        } catch (TaskNotFoundException $exception) {
            throw new NotFoundHttpException(
                message: Yii::t('task', 'Task not found.'),
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
     */
    public function actionDelete(int $id): void
    {
        try {
            $this->taskService->delete($id, $this->currentUserId());
            $this->response->setStatusCode(self::NO_CONTENT);
        } catch (TaskNotFoundException $exception) {
            throw new NotFoundHttpException(
                message: Yii::t('task', 'Task not found.'),
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
