<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\User;
use Yii;
use yii\base\Action;
use yii\filters\auth\HttpBearerAuth;
use yii\rest\Controller;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\UnauthorizedHttpException;

/**
 * Базовый класс для API-контроллеров.
 */
abstract class BaseController extends Controller
{
    protected const int OK = 200;
    protected const int CREATED = 201;
    protected const int NO_CONTENT = 204;
    protected const int BAD_REQUEST = 400;
    protected const int UNAUTHORIZED = 401;
    protected const int FORBIDDEN = 403;
    protected const int NOT_FOUND = 404;
    protected const int METHOD_NOT_ALLOWED = 405;
    protected const int CONFLICT = 409;
    protected const int UNPROCESSABLE_ENTITY = 422;
    protected const int TOO_MANY_REQUESTS = 429;
    protected const int INTERNAL_SERVER_ERROR = 500;
    protected const int SERVICE_UNAVAILABLE = 503;

    /** @var list<string> */
    protected array $publicActions = [];

    /**
     * @return array<string, mixed>
     */
    public function behaviors(): array
    {
        $behaviors = parent::behaviors();
        unset($behaviors['rateLimiter']);
        $behaviors['authenticator'] = [
            'class' => HttpBearerAuth::class,
            'optional' => $this->publicActions,
        ];

        return $behaviors;
    }

    /**
     * @param Action $action
     * @return bool
     * @throws ForbiddenHttpException
     */
    public function beforeAction($action): bool
    {
        try {
            if (!parent::beforeAction($action)) {
                return false;
            }
        } catch (UnauthorizedHttpException $exception) {
            throw new UnauthorizedHttpException('Необходима авторизация.', previous: $exception);
        }

        $isWrite = !in_array(Yii::$app->request->getMethod(), ['GET', 'HEAD', 'OPTIONS'], true);
        if (Yii::$app->params['demoMode']) {
            if (!in_array($action->uniqueId, ['health/index', 'auth/login'], true)) {
                $identity = Yii::$app->user->identity;
                $demoEmail = Yii::$app->params['demoEmail'];
                if (
                    !$identity instanceof User
                    || $demoEmail === ''
                    || mb_strtolower((string) $identity->email) !== $demoEmail
                ) {
                    throw new ForbiddenHttpException('Доступен только демо-аккаунт.');
                }

                if (
                    $isWrite
                    && $action->uniqueId !== 'auth/logout'
                    && !str_starts_with($action->uniqueId, 'task/task/')
                ) {
                    throw new ForbiddenHttpException('Профиль демо-аккаунта изменять нельзя.');
                }
            }
        } elseif (
            YII_ENV_PROD
            && $isWrite
            && !filter_var($_ENV['APP_PUBLIC_WRITES'] ?? false, FILTER_VALIDATE_BOOL)
            && !in_array($action->uniqueId, ['auth/login', 'auth/logout'], true)
        ) {
            throw new ForbiddenHttpException('Запись в публичном API отключена.');
        }

        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    protected function verbs(): array
    {
        return [];
    }

    /**
     * @return int
     * @throws UnauthorizedHttpException
     */
    protected function currentUserId(): int
    {
        $id = Yii::$app->user->id;
        if ($id === null) {
            throw new UnauthorizedHttpException('Необходима авторизация.');
        }

        return (int) $id;
    }

    /**
     * @return array<string, mixed>
     * @throws BadRequestHttpException
     */
    protected function getBodyObject(): array
    {
        $body = $this->request->getBodyParams();
        $contentType = $this->request->getContentType();
        $isJson = str_starts_with(strtolower($contentType), 'application/json');
        if (
            !is_array($body)
            || ($isJson && !str_starts_with(ltrim($this->request->getRawBody()), '{'))
            || (!$isJson && $body !== [] && array_is_list($body))
        ) {
            throw new BadRequestHttpException('Тело запроса должно быть JSON-объектом.');
        }

        return $body;
    }
}
