<?php

declare(strict_types=1);

namespace app\modules\analytics\controllers;

use app\controllers\BaseController;
use app\modules\analytics\forms\AnalyticsFilterForm;
use app\modules\analytics\Module;
use app\modules\analytics\services\AnalyticsService;
use yii\base\InvalidConfigException;
use yii\web\ForbiddenHttpException;

/**
 * Аналитика задач.
 */
final class TaskController extends BaseController
{
    /** @var AnalyticsService Сервис аналитики. */
    private readonly AnalyticsService $analyticsService;

    /**
     * @param string $id
     * @param Module $module
     * @param array<string, mixed> $config
     * @return void
     * @throws InvalidConfigException
     */
    public function __construct(string $id, Module $module, array $config = [])
    {
        /** @var AnalyticsService $analyticsService */
        $analyticsService = $module->get(AnalyticsService::class);
        $this->analyticsService = $analyticsService;

        parent::__construct($id, $module, $config);
    }

    /**
     * @inheritDoc
     * @return array<string, list<string>>
     */
    protected function verbs(): array
    {
        return [
            'index' => ['GET'],
        ];
    }

    /**
     * Получить аналитику задач.
     *
     * @return array<string, int|float|null>|AnalyticsFilterForm
     */
    public function actionIndex(): array|AnalyticsFilterForm
    {
        $form = new AnalyticsFilterForm();
        $form->load($this->request->getQueryParams(), '');

        if (!$form->validate()) {
            return $form;
        }

        $ownerId = $this->currentUserId();
        if ($form->authorId !== null && (int) $form->authorId !== $ownerId) {
            throw new ForbiddenHttpException('Нет доступа к аналитике этого пользователя.');
        }
        $form->authorId = $ownerId;

        return $this->analyticsService->getTasks($form);
    }
}
