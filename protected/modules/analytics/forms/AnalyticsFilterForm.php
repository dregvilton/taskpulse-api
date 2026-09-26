<?php

declare(strict_types=1);

namespace app\modules\analytics\forms;

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
        ];
    }
}
