<?php

declare(strict_types=1);

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\db\Expression;
use yii\web\IdentityInterface;

/**
 * Пользователь.
 *
 * @property int $id
 * @property string $full_name
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $password_hash
 * @property string $created_at
 * @property string $updated_at
 * @property string|null $deleted_at
 */
final class User extends ActiveRecord implements IdentityInterface
{
    /**
     * @param int|string $id
     * @return self|null
     */
    public static function findIdentity($id): ?self
    {
        return self::find()->where(['id' => $id, 'deleted_at' => null])->one();
    }

    /**
     * @param mixed $token
     * @param mixed $type
     * @return self|null
     */
    public static function findIdentityByAccessToken($token, $type = null): ?self
    {
        if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/D', $token)) {
            return null;
        }

        return self::find()
            ->alias('users')
            ->innerJoin('{{%auth_tokens}} tokens', 'tokens.user_id = users.id')
            ->where(['tokens.token_hash' => hash('sha256', $token), 'users.deleted_at' => null])
            ->andWhere("tokens.expires_at > (CURRENT_TIMESTAMP AT TIME ZONE 'UTC')")
            ->one();
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return (int) $this->id;
    }

    /**
     * Сессии и cookie-авторизация не используются.
     *
     * @return null
     */
    public function getAuthKey(): null
    {
        return null;
    }

    /**
     * @param string $authKey
     * @return bool
     */
    public function validateAuthKey($authKey): bool
    {
        return false;
    }

    /**
     * Получить имя таблицы пользователей.
     *
     * @return string
     */
    public static function tableName(): string
    {
        return '{{%users}}';
    }

    /**
     * Настроить временные метки.
     *
     * @return array<string, mixed>
     */
    public function behaviors(): array
    {
        return [
            'timestamp' => [
                'class' => TimestampBehavior::class,
                'createdAtAttribute' => 'created_at',
                'updatedAtAttribute' => 'updated_at',
                'value' => new Expression('CURRENT_TIMESTAMP'),
            ],
        ];
    }

    /**
     * Поля ответа API.
     *
     * @return array<string, string>
     */
    public function fields(): array
    {
        return [
            'id' => 'id',
            'fullName' => 'full_name',
            'phone' => 'phone',
            'email' => 'email',
            'createdAt' => 'created_at',
            'updatedAt' => 'updated_at',
        ];
    }
}
