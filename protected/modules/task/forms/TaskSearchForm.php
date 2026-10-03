<?php

declare(strict_types=1);

namespace app\modules\task\forms;

use DateTimeImmutable;
use DateTimeZone;
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
                ['authorId', 'createdFrom', 'createdTo', 'completedFrom', 'completedTo'],
                'filter',
                'filter' => static fn(mixed $value): mixed => $value === '' ? null : $value,
            ],
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
                    '' => null,
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
                ['createdFrom', 'createdTo', 'completedFrom', 'completedTo'],
                'filter',
                'filter' => static fn(mixed $value): mixed => is_string($value)
                    ? (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s')
                    : $value,
            ],
            [
                'createdFrom',
                'compare',
                'compareAttribute' => 'createdTo',
                'operator' => '<=',
                'when' => static fn(self $form): bool => $form->createdTo !== null && !$form->hasErrors('createdTo'),
                'message' => Yii::t('task', 'Start date must not be later than end date.'),
            ],
            [
                'completedFrom',
                'compare',
                'compareAttribute' => 'completedTo',
                'operator' => '<=',
                'when' => static fn(self $form): bool => $form->completedTo !== null && !$form->hasErrors('completedTo'),
                'message' => Yii::t('task', 'Start date must not be later than end date.'),
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
