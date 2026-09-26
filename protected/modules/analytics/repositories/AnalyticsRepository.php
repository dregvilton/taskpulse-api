<?php

declare(strict_types=1);

namespace app\modules\analytics\repositories;

use app\extensions\DbConnectTrait;
use app\modules\analytics\forms\AnalyticsFilterForm;
use RuntimeException;

/**
 * Репозиторий аналитики задач.
 */
final class AnalyticsRepository
{
    use DbConnectTrait;

    /**
     * Получить агрегированные показатели задач.
     *
     * @param AnalyticsFilterForm $form
     * @return array{
     *     totalCreated: int,
     *     totalCompleted: int,
     *     completionPercent: float,
     *     avgCompletionTimeSeconds: float|null
     * }
     */
    public function getTasks(AnalyticsFilterForm $form): array
    {
        $row = $this->getDbConnection()
            ->createCommand(__DIR__ . '/sqls/get_task_analytics.sql')
            ->bindValues([
                ':authorId' => $form->authorId,
                ':createdFrom' => $form->createdFrom,
                ':createdTo' => $form->createdTo,
            ])
            ->queryOne();

        if ($row === false) {
            throw new RuntimeException('Не удалось получить аналитику задач.');
        }

        return [
            'totalCreated' => (int) $row['totalCreated'],
            'totalCompleted' => (int) $row['totalCompleted'],
            'completionPercent' => (float) $row['completionPercent'],
            'avgCompletionTimeSeconds' => $row['avgCompletionTimeSeconds'] === null
                ? null
                : (float) $row['avgCompletionTimeSeconds'],
        ];
    }
}
