<?php

declare(strict_types=1);

namespace app\modules\user\forms;

use app\models\User;
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
    /** @var mixed Почта для входа. */
    public mixed $email = null;
    /** @var mixed Пароль для входа. */
    public mixed $password = null;
    /** @var list<string> */
    private array $providedFields = [];

    /**
     * @inheritDoc
     * @param array<string, mixed> $data
     * @param string|null $formName
     * @return bool
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
            ['email', 'trim', 'skipOnArray' => true, 'on' => self::SCENARIO_CREATE],
            ['email', 'filter', 'filter' => 'mb_strtolower', 'skipOnArray' => true, 'on' => self::SCENARIO_CREATE],
            ['email', 'required', 'message' => Yii::t('user', 'Email is required.'), 'on' => self::SCENARIO_CREATE],
            ['email', 'email', 'message' => Yii::t('user', 'Email is invalid.'), 'on' => self::SCENARIO_CREATE],
            [
                'email',
                'unique',
                'targetClass' => User::class,
                'filter' => ['deleted_at' => null],
                'message' => Yii::t('user', 'Email is already taken.'),
                'on' => self::SCENARIO_CREATE,
            ],
            ['password', 'required', 'message' => Yii::t('user', 'Password is required.'), 'on' => self::SCENARIO_CREATE],
            [
                'password',
                'string',
                'min' => 12,
                'max' => 255,
                'tooShort' => Yii::t('user', 'Password must contain at least 12 characters.'),
                'tooLong' => Yii::t('user', 'Password must contain at most 255 characters.'),
                'on' => self::SCENARIO_CREATE,
            ],
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
            : ['fullName', 'phone', 'email'];
        $attributes = $this->getAttributes($fields);

        if (array_key_exists('fullName', $attributes)) {
            $attributes['full_name'] = $attributes['fullName'];
            unset($attributes['fullName']);
        }

        return $attributes;
    }

    /**
     * Проверить наличие данных для обновления.
     *
     * @return void
     */
    public function validateChanges(): void
    {
        if ($this->providedFields === []) {
            $this->addError('fullName', Yii::t('app', 'No data provided for update.'));
        }
    }
}
