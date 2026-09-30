<?php

declare(strict_types=1);

namespace app\components;

use yii\filters\auth\HttpBearerAuth;
use yii\web\Response;
use yii\web\UnauthorizedHttpException;

/**
 * Проверка Bearer-токена с сообщением API на русском языке.
 */
final class BearerAuth extends HttpBearerAuth
{
    /**
     * @param Response $response
     * @return never
     * @throws UnauthorizedHttpException
     */
    public function handleFailure($response): never
    {
        throw new UnauthorizedHttpException('Необходима авторизация.');
    }
}
