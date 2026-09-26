<?php

declare(strict_types=1);

namespace app\modules\analytics\services;

use app\modules\analytics\forms\AnalyticsFilterForm;
use app\modules\analytics\repositories\AnalyticsRepository;
use app\services\AnalyticsCache;

/**
 * Сервис аналитики задач.
 */
final readonly class AnalyticsService
{
    public function __construct(
        private AnalyticsRepository $repository,
        private AnalyticsCache $cache,
    ) {}

    /**
     * Получить показатели задач.
     *
     * @param AnalyticsFilterForm $form
     * @return array<string, int|float|null>
     */
    public function getTasks(AnalyticsFilterForm $form): array
    {
        return $this->cache->getOrSet(
            $form,
            fn(): array => $this->repository->getTasks($form),
        );
    }
}
