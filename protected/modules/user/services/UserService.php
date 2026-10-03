<?php

declare(strict_types=1);

namespace app\modules\user\services;

use app\models\User;
use app\modules\user\exceptions\UserEmailTakenException;
use app\modules\user\exceptions\UserNotFoundException;
use app\modules\user\exceptions\UserSaveException;
use app\modules\user\forms\UserForm;
use Yii;
use yii\data\ActiveDataProvider;
use yii\db\Expression;
use yii\db\IntegrityException;

/**
 * Сервис управления пользователями.
 */
final class UserService
{
    /**
     * Создать пользователя.
     *
     * @param UserForm $form
     * @return User
     * @throws IntegrityException|UserEmailTakenException|UserSaveException
     */
    public function create(UserForm $form): User
    {
        $user = new User();
        $user->setAttributes($form->getUserAttributes(), false);
        $user->setAttribute('password_hash', Yii::$app->security->generatePasswordHash((string) $form->password));

        try {
            $this->save($user);
        } catch (IntegrityException $exception) {
            if (
                ($exception->errorInfo[0] ?? null) === '23505'
                && str_contains((string) ($exception->errorInfo[2] ?? ''), 'uq-users-active-email')
            ) {
                throw new UserEmailTakenException('Почта уже используется.', previous: $exception);
            }

            throw $exception;
        }
        $user->refresh();

        return $user;
    }

    /**
     * Получить пользователя.
     *
     * @param int $id
     * @return User
     * @throws UserNotFoundException
     */
    public function getById(int $id): User
    {
        $user = User::find()
            ->where(['id' => $id, 'deleted_at' => null])
            ->one();

        if (!$user instanceof User) {
            throw new UserNotFoundException("Пользователь {$id} не найден.");
        }

        return $user;
    }

    /**
     * Получить список пользователей.
     *
     * @param int $currentUserId
     * @return ActiveDataProvider
     */
    public function getList(int $currentUserId): ActiveDataProvider
    {
        return new ActiveDataProvider([
            'query' => User::find()
                ->where(['id' => $currentUserId, 'deleted_at' => null])
                ->orderBy(['id' => SORT_ASC]),
            'pagination' => [
                'defaultPageSize' => 20,
                'pageSizeLimit' => [1, 100],
                'pageParam' => 'page',
                'pageSizeParam' => 'perPage',
            ],
        ]);
    }

    /**
     * Обновить пользователя.
     *
     * @param int $id
     * @param UserForm $form
     * @return User
     * @throws UserNotFoundException
     * @throws IntegrityException|UserSaveException
     */
    public function update(int $id, UserForm $form): User
    {
        $user = $this->getById($id);
        $user->setAttributes($form->getUserAttributes(), false);

        $this->save($user);
        $user->refresh();

        return $user;
    }

    /**
     * Удалить пользователя.
     *
     * @param int $id
     * @return void
     * @throws UserNotFoundException
     * @throws UserSaveException
     */
    public function delete(int $id): void
    {
        $user = $this->getById($id);
        $user->setAttribute('deleted_at', new Expression('CURRENT_TIMESTAMP'));

        $this->save($user);
        Yii::$app->db->createCommand()->delete('{{%auth_tokens}}', ['user_id' => $id])->execute();
    }

    /**
     * Сохранить пользователя.
     *
     * @param User $user
     * @return void
     * @throws IntegrityException|UserSaveException
     */
    private function save(User $user): void
    {
        if (!$user->save(false)) {
            throw new UserSaveException('Не удалось сохранить пользователя.');
        }
    }
}
