<?php

declare(strict_types=1);

namespace app\modules\user\forms;

use Yii;
use yii\base\Model;

/**
 * Форма создания и обновления пользователя.
 */
final class UserForm extends Model
{
    public const string SCENARIO_CREATE = 'create';
    public const string SCENARIO_UPDATE = 'update';

    /** @var mixed Полное имя. */
    public mixed $fullName = null;

    /** @var mixed Телефон. */
    public mixed $phone = null;

    /** @var list<string> */
    private array $providedFields = [];

    /**
     * @inheritDoc
     * @param array<string, mixed> $data
     */
    public function load($data, $formName = null): bool
    {
        $this->providedFields = array_values(
            array_intersect(['fullName', 'phone'], array_keys($data)),
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
                'fullName',
                'string',
                'message' => Yii::t('user', 'Full name must be a string.'),
            ],
            ['fullName', 'trim'],
            [
                'fullName',
                'required',
                'message' => Yii::t('user', 'Full name is required.'),
                'on' => self::SCENARIO_CREATE,
            ],
            [
                'fullName',
                'required',
                'message' => Yii::t('user', 'Full name is required.'),
                'when' => fn(self $form): bool => $form->hasField('fullName'),
                'on' => self::SCENARIO_UPDATE,
            ],
            [
                'fullName',
                'validateChanges',
                'skipOnEmpty' => false,
                'on' => self::SCENARIO_UPDATE,
            ],
            [
                'fullName',
                'string',
                'min' => 3,
                'max' => 100,
                'message' => Yii::t('user', 'Full name must be a string.'),
                'tooShort' => Yii::t('user', 'Full name should contain at least 3 characters.'),
                'tooLong' => Yii::t('user', 'Full name should contain at most 100 characters.'),
            ],
            [
                'phone',
                'string',
                'min' => 10,
                'max' => 15,
                'message' => Yii::t('user', 'Phone must be a string.'),
                'tooShort' => Yii::t('user', 'Phone should contain at least 10 characters.'),
                'tooLong' => Yii::t('user', 'Phone should contain at most 15 characters.'),
            ],
            [
                'phone',
                'match',
                'pattern' => '/^\+\d{9,14}$/',
                'message' => Yii::t('user', 'Phone must start with + and contain digits only.'),
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
     * Получить атрибуты пользователя.
     *
     * @return array<string, mixed>
     */
    public function getUserAttributes(): array
    {
        $fields = $this->scenario === self::SCENARIO_UPDATE
            ? $this->providedFields
            : ['fullName', 'phone'];
        $attributes = $this->getAttributes($fields);

        if (array_key_exists('fullName', $attributes)) {
            $attributes['full_name'] = $attributes['fullName'];
            unset($attributes['fullName']);
        }

        return $attributes;
    }

    /**
     * Проверить наличие данных для обновления.
     */
    public function validateChanges(): void
    {
        if ($this->providedFields === []) {
            $this->addError('fullName', Yii::t('app', 'No data provided for update.'));
        }
    }
}
