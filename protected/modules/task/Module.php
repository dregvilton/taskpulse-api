<?php

declare(strict_types=1);

namespace app\modules\task;

use app\modules\task\repositories\TaskRepository;
use app\modules\task\services\TaskService;

/**
 * Модуль задач.
 */
final class Module extends \yii\base\Module
{
    /** @inheritDoc */
    public $controllerNamespace = 'app\\modules\\task\\controllers';

    /** @inheritDoc */
    public function init(): void
    {
        parent::init();

        $this->set(TaskRepository::class, [
            'class' => TaskRepository::class,
        ]);
        $this->set(TaskService::class, function (): TaskService {
            /** @var TaskRepository $repository */
            $repository = $this->get(TaskRepository::class);

            return new TaskService($repository);
        });
    }
}
