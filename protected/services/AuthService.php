<?php

declare(strict_types=1);

namespace app\services;

use app\extensions\DbConnectTrait;
use app\forms\LoginForm;
use app\models\User;
use Yii;
use yii\base\Exception as SecurityException;
use yii\base\InvalidConfigException;
use yii\db\Exception;

/**
 * Выдача и отзыв токенов доступа.
 */
final class AuthService
{
    use DbConnectTrait;

    private const int TOKEN_TTL_SECONDS = 3600;

    /**
     * @param LoginForm $form
     * @return array{tokenType: string, accessToken: string, expiresAt: string, userId: int}|null
     * @throws InvalidConfigException|Exception|SecurityException
     */
    public function login(LoginForm $form): ?array
    {
        $user = User::find()
            ->where(['email' => $form->email, 'deleted_at' => null])
            ->one();
        if (
            !$user instanceof User
            || $user->password_hash === null
            || !Yii::$app->security->validatePassword((string) $form->password, $user->password_hash)
        ) {
            return null;
        }

        $token = Yii::$app->security->generateRandomString(64);
        $expiresAt = time() + self::TOKEN_TTL_SECONDS;
        $this->getDbConnection()->createCommand()->insert('{{%auth_tokens}}', [
            'token_hash' => hash('sha256', $token),
            'user_id' => $user->id,
            'expires_at' => gmdate('Y-m-d H:i:s', $expiresAt),
        ])->execute();

        return [
            'tokenType' => 'Bearer',
            'accessToken' => $token,
            'expiresAt' => gmdate('Y-m-d\TH:i:s\Z', $expiresAt),
            'userId' => (int) $user->id,
        ];
    }

    /**
     * @param string $tokenHash
     * @return void
     * @throws InvalidConfigException|Exception
     */
    public function logout(string $tokenHash): void
    {
        $this->getDbConnection()->createCommand()->delete('{{%auth_tokens}}', [
            'token_hash' => $tokenHash,
        ])->execute();
    }
}
