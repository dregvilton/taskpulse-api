<?php

declare(strict_types=1);

namespace app\modules\user\forms;

use Yii;
use yii\base\Model;

/**
 * Форма пагинации списка пользователей.
 */
final class UserSearchForm extends Model
{
    /** @var mixed Номер страницы. */
    public mixed $page = 1;

    /** @var mixed Размер страницы. */
    public mixed $perPage = 20;

    /**
     * @inheritDoc
     * @return array<int, mixed>
     */
    public function rules(): array
    {
        return [
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
        ];
    }
}
