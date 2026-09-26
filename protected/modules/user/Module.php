<?php

declare(strict_types=1);

namespace app\modules\user;

use app\modules\user\services\UserService;

/**
 * Модуль пользователей.
 */
final class Module extends \yii\base\Module
{
    /** @inheritDoc */
    public $controllerNamespace = 'app\\modules\\user\\controllers';

    /**
     * @inheritDoc
     * @return void
     */
    public function init(): void
    {
        parent::init();

        $this->set(UserService::class, [
            'class' => UserService::class,
        ]);
    }
}
