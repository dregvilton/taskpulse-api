<?php

declare(strict_types=1);

namespace app\modules\analytics\services;

use app\modules\analytics\forms\AnalyticsFilterForm;
use app\modules\analytics\repositories\AnalyticsRepository;
use app\services\AnalyticsCache;
use RuntimeException;
use yii\base\InvalidConfigException;
use yii\db\Exception;

/**
 * Сервис аналитики задач.
 */
final readonly class AnalyticsService
{
    /**
     * @param AnalyticsRepository $repository
     * @param AnalyticsCache $cache
     * @return void
     */
    public function __construct(
        private AnalyticsRepository $repository,
        private AnalyticsCache $cache,
    ) {}

    /**
     * Получить показатели задач.
     *
     * @param AnalyticsFilterForm $form
     * @return array{
     *     totalCreated: int,
     *     totalCompleted: int,
     *     completionPercent: float,
     *     avgCompletionTimeSeconds: float|null
     * }
     * @throws InvalidConfigException|Exception|RuntimeException
     */
    public function getTasks(AnalyticsFilterForm $form): array
    {
        return $this->cache->getOrSet(
            $form,
            fn(): array => $this->format($this->repository->getTasks($form)),
        );
    }

    /**
     * Подготовить показатели для ответа API.
     *
     * @param array<string, mixed>|false $row
     * @return array{
     *     totalCreated: int,
     *     totalCompleted: int,
     *     completionPercent: float,
     *     avgCompletionTimeSeconds: float|null
     * }
     * @throws RuntimeException
     */
    private function format(array|false $row): array
    {
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
