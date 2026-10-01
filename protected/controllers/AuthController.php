<?php

declare(strict_types=1);

namespace app\controllers;

use app\forms\LoginForm;
use app\models\User;
use app\services\AuthService;
use Yii;
use yii\base\InvalidConfigException;
use yii\base\Module;
use yii\web\UnauthorizedHttpException;

/**
 * Вход и выход из API.
 */
final class AuthController extends BaseController
{
    /** @var list<string> */
    protected array $publicActions = ['login'];

    private readonly AuthService $authService;

    /**
     * @param string $id
     * @param Module $module
     * @param array<string, mixed> $config
     * @return void
     * @throws InvalidConfigException
     */
    public function __construct(string $id, Module $module, array $config = [])
    {
        /** @var AuthService $authService */
        $authService = Yii::$app->get('authService');
        $this->authService = $authService;

        parent::__construct($id, $module, $config);
    }

    /**
     * @return array<string, list<string>>
     */
    protected function verbs(): array
    {
        return ['login' => ['POST'], 'logout' => ['POST']];
    }

    /**
     * @return array<string, int|string>|LoginForm
     * @throws UnauthorizedHttpException
     */
    public function actionLogin(): array|LoginForm
    {
        $form = new LoginForm();
        $form->load($this->request->getBodyParams(), '');
        if (!$form->validate()) {
            return $form;
        }

        $result = $this->authService->login($form);
        if ($result === null) {
            throw new UnauthorizedHttpException(Yii::t('user', 'Invalid credentials.'));
        }

        return $result;
    }

    /**
     * @return void
     */
    public function actionLogout(): void
    {
        $identity = Yii::$app->user->identity;
        $tokenHash = $identity instanceof User ? $identity->getCurrentTokenHash() : null;
        if ($tokenHash === null) {
            throw new UnauthorizedHttpException('Необходима авторизация.');
        }

        $this->authService->logout($tokenHash);
        $this->response->setStatusCode(self::NO_CONTENT);
    }
}
