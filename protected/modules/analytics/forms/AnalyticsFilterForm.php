<?php

declare(strict_types=1);

namespace app\modules\analytics\forms;

use DateTimeImmutable;
use DateTimeZone;
use Yii;
use yii\base\Model;
use yii\validators\DateValidator;

/**
 * Форма фильтрации аналитики задач.
 */
final class AnalyticsFilterForm extends Model
{
    /** @var mixed ID автора. */
    public mixed $authorId = null;
    /** @var mixed Начало периода создания. */
    public mixed $createdFrom = null;
    /** @var mixed Конец периода создания. */
    public mixed $createdTo = null;

    /**
     * @inheritDoc
     * @return array<int, mixed>
     */
    public function rules(): array
    {
        return [
            [
                ['authorId', 'createdFrom', 'createdTo'],
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
                ['createdFrom', 'createdTo'],
                'date',
                'type' => DateValidator::TYPE_DATETIME,
                'format' => 'php:Y-m-d\TH:i:sP',
                'message' => Yii::t('task', 'Date must be in ISO 8601 format.'),
            ],
            [
                ['createdFrom', 'createdTo'],
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
        ];
    }
}
