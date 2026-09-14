<?php

declare(strict_types=1);

namespace app\modules\task\forms;

use Yii;
use yii\base\Model;
use yii\validators\DateValidator;

/**
 * Форма фильтрации списка задач.
 */
final class TaskSearchForm extends Model
{
    /** @var mixed ID автора. */
    public mixed $authorId = null;

    /** @var mixed Признак завершения. */
    public mixed $completed = null;

    /** @var mixed Начало периода создания. */
    public mixed $createdFrom = null;

    /** @var mixed Конец периода создания. */
    public mixed $createdTo = null;

    /** @var mixed Начало периода завершения. */
    public mixed $completedFrom = null;

    /** @var mixed Конец периода завершения. */
    public mixed $completedTo = null;

    /** @var mixed Номер страницы. */
    public mixed $page = 1;

    /** @var mixed Размер страницы. */
    public mixed $perPage = 20;

    /** @var mixed Сортировка. */
    public mixed $sort = null;

    /**
     * @inheritDoc
     * @return array<int, mixed>
     */
    public function rules(): array
    {
        return [
            [
                'authorId',
                'integer',
                'min' => 1,
                'message' => Yii::t('task', 'Author ID must be an integer.'),
                'tooSmall' => Yii::t('task', 'Author ID must be no less than 1.'),
            ],
            [
                'completed',
                'filter',
                'filter' => static fn(mixed $value): mixed => match ($value) {
                    'true' => true,
                    'false' => false,
                    default => $value,
                },
            ],
            [
                'completed',
                'boolean',
                'trueValue' => true,
                'falseValue' => false,
                'strict' => true,
                'message' => Yii::t('task', 'Completed must be a boolean.'),
            ],
            [
                ['createdFrom', 'createdTo', 'completedFrom', 'completedTo'],
                'date',
                'type' => DateValidator::TYPE_DATETIME,
                'format' => 'php:Y-m-d\TH:i:sP',
                'message' => Yii::t('task', 'Date must be in ISO 8601 format.'),
            ],
            [
                'page',
                'integer',
                'min' => 1,
                'skipOnEmpty' => false,
                'message' => Yii::t('app', 'Page must be an integer.'),
                'tooSmall' => Yii::t('app', 'Page must be no less than 1.'),
            ],
            [
                'perPage',
                'integer',
                'min' => 1,
                'max' => 100,
                'skipOnEmpty' => false,
                'message' => Yii::t('app', 'Page size must be an integer.'),
                'tooSmall' => Yii::t('app', 'Page size must be no less than 1.'),
                'tooBig' => Yii::t('app', 'Page size must be no greater than 100.'),
            ],
            [
                'sort',
                'match',
                'pattern' => '/^-?(?:id|title|completed|createdAt|completedAt)(?:,-?(?:id|title|completed|createdAt|completedAt))*$/',
                'message' => Yii::t('task', 'Sort value is invalid.'),
            ],
        ];
    }
}
