<?php

declare(strict_types=1);

namespace app\modules\user\controllers;

use app\controllers\BaseController;
use app\models\User;
use app\modules\user\exceptions\UserNotFoundException;
use app\modules\user\forms\UserForm;
use app\modules\user\forms\UserSearchForm;
use app\modules\user\Module;
use app\modules\user\services\UserService;
use Yii;
use yii\base\InvalidConfigException;
use yii\data\ActiveDataProvider;
use yii\helpers\Url;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

/**
 * Управление пользователями.
 */
final class UserController extends BaseController
{
    /** @var UserService Сервис пользователей. */
    private readonly UserService $userService;

    /**
     * @param string $id
     * @param Module $module
     * @param array<string, mixed> $config
     * @return void
     * @throws InvalidConfigException
     */
    public function __construct(string $id, Module $module, array $config = [])
    {
        /** @var UserService $userService */
        $userService = $module->get(UserService::class);
        $this->userService = $userService;

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
            'view' => ['GET'],
            'create' => ['POST'],
            'update' => ['PATCH'],
            'delete' => ['DELETE'],
        ];
    }

    /**
     * @return list<string>
     */
    protected function publicActions(): array
    {
        return ['create'];
    }

    /**
     * Получить список пользователей.
     *
     * @return ActiveDataProvider|UserSearchForm
     */
    public function actionIndex(): ActiveDataProvider|UserSearchForm
    {
        $form = new UserSearchForm();
        $form->load($this->request->getQueryParams(), '');

        if (!$form->validate()) {
            return $form;
        }

        return $this->userService->getList($this->currentUserId());
    }

    /**
     * Получить пользователя.
     *
     * @param int $id
     * @return User
     * @throws NotFoundHttpException
     */
    public function actionView(int $id): User
    {
        $this->assertOwnUser($id);
        try {
            return $this->userService->getById($id);
        } catch (UserNotFoundException $exception) {
            throw new NotFoundHttpException(
                message: Yii::t('user', 'User not found.'),
                previous: $exception,
            );
        }
    }

    /**
     * Создать пользователя.
     *
     * @return User|UserForm
     */
    public function actionCreate(): User|UserForm
    {
        $form = new UserForm(['scenario' => UserForm::SCENARIO_CREATE]);
        $form->load($this->request->getBodyParams(), '');

        if (!$form->validate()) {
            return $form;
        }

        $user = $this->userService->create($form);
        $this->response->setStatusCode(self::CREATED);
        $this->response->headers->set(
            'Location',
            Url::toRoute(['/user/user/view', 'id' => $user->id]),
        );

        return $user;
    }

    /**
     * Обновить пользователя.
     *
     * @param int $id
     * @return User|UserForm
     * @throws NotFoundHttpException
     */
    public function actionUpdate(int $id): User|UserForm
    {
        $this->assertOwnUser($id);
        $form = new UserForm(['scenario' => UserForm::SCENARIO_UPDATE]);
        $form->load($this->request->getBodyParams(), '');

        if (!$form->validate()) {
            return $form;
        }

        try {
            return $this->userService->update($id, $form);
        } catch (UserNotFoundException $exception) {
            throw new NotFoundHttpException(
                message: Yii::t('user', 'User not found.'),
                previous: $exception,
            );
        }
    }

    /**
     * Удалить пользователя.
     *
     * @param int $id
     * @return void
     * @throws NotFoundHttpException
     */
    public function actionDelete(int $id): void
    {
        $this->assertOwnUser($id);
        try {
            $this->userService->delete($id);
            $this->response->setStatusCode(self::NO_CONTENT);
        } catch (UserNotFoundException $exception) {
            throw new NotFoundHttpException(
                message: Yii::t('user', 'User not found.'),
                previous: $exception,
            );
        }
    }

    /**
     * @param int $id
     * @return void
     * @throws ForbiddenHttpException
     */
    private function assertOwnUser(int $id): void
    {
        if ($id !== $this->currentUserId()) {
            throw new ForbiddenHttpException('Нет доступа к этому пользователю.');
        }
    }
}
