<?php

declare(strict_types=1);

namespace app\modules\analytics\repositories;

use app\extensions\DbConnectTrait;
use app\modules\analytics\forms\AnalyticsFilterForm;
use RuntimeException;
use yii\base\InvalidConfigException;
use yii\db\Exception;

/**
 * Репозиторий аналитики задач.
 */
final class AnalyticsRepository
{
    use DbConnectTrait;

    /**
     * Получить строку с агрегированными показателями задач.
     *
     * @param AnalyticsFilterForm $form
     * @return array<string, mixed>|false
     * @throws InvalidConfigException|Exception|RuntimeException
     */
    public function getTasks(AnalyticsFilterForm $form): array|false
    {
        return $this->getDbConnection()
            ->createCommand(__DIR__ . '/sqls/get_task_analytics.sql')
            ->bindValues([
                ':authorId' => $form->authorId,
                ':createdFrom' => $form->createdFrom,
                ':createdTo' => $form->createdTo,
            ])
            ->queryOne();
    }
}
