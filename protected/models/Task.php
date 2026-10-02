<?php

declare(strict_types=1);

namespace app\models;

use Closure;
use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\db\Expression;

/**
 * Задача.
 *
 * @property int $id
 * @property int $author_id
 * @property string $title
 * @property string|null $description
 * @property bool $completed
 * @property string $created_at
 * @property string $updated_at
 * @property string|null $completed_at
 * @property string|null $deleted_at
 * @property-read User $author
 */
final class Task extends ActiveRecord
{
    /**
     * Получить имя таблицы задач.
     *
     * @return string
     */
    public static function tableName(): string
    {
        return '{{%tasks}}';
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
     * Получить автора задачи.
     *
     * @return ActiveQuery<User>
     */
    public function getAuthor(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'author_id']);
    }

    /**
     * Поля ответа API.
     *
     * @return array<string, string|Closure>
     */
    public function fields(): array
    {
        return [
            'id' => 'id',
            'authorId' => 'author_id',
            'title' => 'title',
            'description' => 'description',
            'completed' => 'completed',
            'createdAt' => static fn(self $task): string => Yii::$app->formatter->asDatetime($task->created_at),
            'updatedAt' => static fn(self $task): string => Yii::$app->formatter->asDatetime($task->updated_at),
            'completedAt' => static fn(self $task): ?string => $task->completed_at === null
                ? null
                : Yii::$app->formatter->asDatetime($task->completed_at),
        ];
    }
}
