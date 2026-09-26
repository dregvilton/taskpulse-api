<?php

declare(strict_types=1);

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\db\Expression;

/**
 * Пользователь.
 *
 * @property int $id
 * @property string $full_name
 * @property string|null $phone
 * @property string $created_at
 * @property string $updated_at
 * @property string|null $deleted_at
 */
final class User extends ActiveRecord
{
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
            'createdAt' => 'created_at',
            'updatedAt' => 'updated_at',
        ];
    }
}
