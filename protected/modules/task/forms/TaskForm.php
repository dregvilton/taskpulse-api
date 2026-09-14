<?php

declare(strict_types=1);

namespace app\modules\task\forms;

use app\models\User;
use Yii;
use yii\base\Model;

/**
 * Форма создания и обновления задачи.
 */
final class TaskForm extends Model
{
    public const string SCENARIO_CREATE = 'create';
    public const string SCENARIO_UPDATE = 'update';

    /** @var mixed ID автора. */
    public mixed $authorId = null;

    /** @var mixed Заголовок. */
    public mixed $title = null;

    /** @var mixed Описание. */
    public mixed $description = null;

    /** @var mixed Признак завершения. */
    public mixed $completed = false;

    /** @var list<string> */
    private array $providedFields = [];

    /**
     * @inheritDoc
     * @param array<string, mixed> $data
     */
    public function load($data, $formName = null): bool
    {
        $this->providedFields = array_values(
            array_intersect(['authorId', 'title', 'description', 'completed'], array_keys($data)),
        );

        return parent::load($data, $formName);
    }

    /**
     * @inheritDoc
     * @return array<int, mixed>
     */
    public function rules(): array
    {
        return [
            [
                'authorId',
                'required',
                'message' => Yii::t('task', 'Author is required.'),
                'on' => self::SCENARIO_CREATE,
            ],
            [
                'authorId',
                'required',
                'message' => Yii::t('task', 'Author is required.'),
                'when' => fn(self $form): bool => $form->hasField('authorId'),
                'on' => self::SCENARIO_UPDATE,
            ],
            [
                'authorId',
                'integer',
                'min' => 1,
                'message' => Yii::t('task', 'Author ID must be an integer.'),
                'tooSmall' => Yii::t('task', 'Author ID must be no less than 1.'),
            ],
            [
                'authorId',
                'exist',
                'targetClass' => User::class,
                'targetAttribute' => ['authorId' => 'id'],
                'filter' => ['deleted_at' => null],
                'message' => Yii::t('task', 'Author not found.'),
            ],
            [
                'title',
                'string',
                'message' => Yii::t('task', 'Title must be a string.'),
            ],
            ['title', 'trim'],
            [
                'title',
                'required',
                'message' => Yii::t('task', 'Title is required.'),
                'on' => self::SCENARIO_CREATE,
            ],
            [
                'title',
                'required',
                'message' => Yii::t('task', 'Title is required.'),
                'when' => fn(self $form): bool => $form->hasField('title'),
                'on' => self::SCENARIO_UPDATE,
            ],
            [
                'title',
                'validateChanges',
                'skipOnEmpty' => false,
                'on' => self::SCENARIO_UPDATE,
            ],
            [
                'title',
                'string',
                'min' => 3,
                'max' => 255,
                'message' => Yii::t('task', 'Title must be a string.'),
                'tooShort' => Yii::t('task', 'Title should contain at least 3 characters.'),
                'tooLong' => Yii::t('task', 'Title should contain at most 255 characters.'),
            ],
            [
                'description',
                'string',
                'max' => 5000,
                'message' => Yii::t('task', 'Description must be a string.'),
                'tooLong' => Yii::t('task', 'Description should contain at most 5000 characters.'),
            ],
            [
                'completed',
                'boolean',
                'trueValue' => true,
                'falseValue' => false,
                'strict' => true,
                'message' => Yii::t('task', 'Completed must be a boolean.'),
            ],
        ];
    }

    /**
     * Проверить наличие поля в запросе.
     *
     * @param string $field
     * @return bool
     */
    public function hasField(string $field): bool
    {
        return in_array($field, $this->providedFields, true);
    }

    /**
     * Получить атрибуты задачи.
     *
     * @return array<string, mixed>
     */
    public function getTaskAttributes(): array
    {
        $fields = $this->scenario === self::SCENARIO_UPDATE
            ? $this->providedFields
            : ['authorId', 'title', 'description', 'completed'];
        $attributes = $this->getAttributes($fields);

        if (array_key_exists('authorId', $attributes)) {
            $attributes['author_id'] = $attributes['authorId'];
            unset($attributes['authorId']);
        }

        return $attributes;
    }

    /**
     * Проверить наличие данных для обновления.
     */
    public function validateChanges(): void
    {
        if ($this->providedFields === []) {
            $this->addError('title', Yii::t('app', 'No data provided for update.'));
        }
    }
}
