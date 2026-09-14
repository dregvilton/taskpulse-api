<?php

declare(strict_types=1);

namespace app\models;

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
    public static function tableName(): string
    {
        return '{{%tasks}}';
    }

    /**
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
     * @return array<string, string>
     */
    public function fields(): array
    {
        return [
            'id' => 'id',
            'authorId' => 'author_id',
            'title' => 'title',
            'description' => 'description',
            'completed' => 'completed',
            'createdAt' => 'created_at',
            'updatedAt' => 'updated_at',
            'completedAt' => 'completed_at',
        ];
    }
}
