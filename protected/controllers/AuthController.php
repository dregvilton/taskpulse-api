<?php

declare(strict_types=1);

namespace app\controllers;

use app\forms\LoginForm;
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
     * @return list<string>
     */
    protected function publicActions(): array
    {
        return ['login'];
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
        $authorization = $this->request->headers->get('Authorization', '');
        preg_match('/^Bearer\s+(.+)$/', $authorization, $matches);
        $this->authService->logout($matches[1] ?? '');
        $this->response->setStatusCode(self::NO_CONTENT);
    }
}
