<?php

declare(strict_types=1);

namespace app\modules\task;

use app\modules\task\repositories\IdempotencyRepository;
use app\modules\task\repositories\TaskRepository;
use app\modules\task\services\TaskService;
use app\services\AnalyticsCache;
use Yii;
use yii\base\InvalidConfigException;

/**
 * Модуль задач.
 */
final class Module extends \yii\base\Module
{
    /** @inheritDoc */
    public $controllerNamespace = 'app\\modules\\task\\controllers';

    /**
     * @inheritDoc
     * @return void
     */
    public function init(): void
    {
        parent::init();

        $this->set(TaskRepository::class, [
            'class' => TaskRepository::class,
        ]);
        $this->set(IdempotencyRepository::class, [
            'class' => IdempotencyRepository::class,
        ]);
        $this->set(TaskService::class, function (): TaskService {
            /** @var TaskRepository $repository */
            $repository = $this->get(TaskRepository::class);
            /** @var IdempotencyRepository $idempotencyRepository */
            $idempotencyRepository = $this->get(IdempotencyRepository::class);
            $analyticsCache = Yii::$app->get('analyticsCache', false);
            if (!$analyticsCache instanceof AnalyticsCache) {
                throw new InvalidConfigException('Кеш аналитики не настроен.');
            }

            return new TaskService($repository, $idempotencyRepository, $analyticsCache);
        });
    }
}
