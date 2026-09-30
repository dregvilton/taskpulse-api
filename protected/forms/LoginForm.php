<?php

declare(strict_types=1);

namespace app\forms;

use Yii;
use yii\base\Model;

/**
 * Данные для входа.
 */
final class LoginForm extends Model
{
    /** @var mixed Почта. */
    public mixed $email = null;
    /** @var mixed Пароль. */
    public mixed $password = null;

    /**
     * @return array<int, mixed>
     */
    public function rules(): array
    {
        return [
            ['email', 'trim', 'skipOnArray' => true],
            ['email', 'filter', 'filter' => 'mb_strtolower', 'skipOnArray' => true],
            [['email', 'password'], 'required', 'message' => Yii::t('user', 'Credentials are required.')],
            ['email', 'email', 'message' => Yii::t('user', 'Email is invalid.')],
            ['password', 'string'],
        ];
    }
}
