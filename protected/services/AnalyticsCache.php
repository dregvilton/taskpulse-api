<?php

declare(strict_types=1);

namespace app\services;

use app\modules\analytics\forms\AnalyticsFilterForm;
use JsonException;
use Throwable;
use Yii;
use yii\redis\Connection;

/**
 * Кеш аналитики задач.
 */
final readonly class AnalyticsCache
{
    private const string GENERATION_KEY = 'taskpulse:analytics:tasks:generation';
    private const int TTL = 300;

    public function __construct(private Connection $redis) {}

    /**
     * Получить результат из кеша или выполнить расчёт.
     *
     * @param AnalyticsFilterForm $form
     * @param callable(): array<string, int|float|null> $calculate
     * @return array<string, int|float|null>
     */
    public function getOrSet(AnalyticsFilterForm $form, callable $calculate): array
    {
        try {
            $generation = $this->redis->executeCommand('GET', [self::GENERATION_KEY]);
            $key = $this->getKey($form, (int) $generation);
            $cached = $this->redis->executeCommand('GET', [$key]);

            if (is_string($cached)) {
                /** @var array<string, int|float|null> $result */
                $result = json_decode($cached, true, flags: JSON_THROW_ON_ERROR);

                return $result;
            }
        } catch (Throwable $exception) {
            Yii::warning($exception, __METHOD__);

            return $calculate();
        }

        $result = $calculate();

        try {
            $this->redis->executeCommand('SETEX', [
                $key,
                self::TTL,
                json_encode($result, JSON_THROW_ON_ERROR),
            ]);
        } catch (Throwable $exception) {
            Yii::warning($exception, __METHOD__);
        }

        return $result;
    }

    /**
     * Сбросить кеш после изменения задачи.
     */
    public function invalidate(): void
    {
        try {
            $this->redis->executeCommand('INCR', [self::GENERATION_KEY]);
        } catch (Throwable $exception) {
            Yii::warning($exception, __METHOD__);
        }
    }

    /**
     * Создать ключ для набора фильтров.
     *
     * @param AnalyticsFilterForm $form
     * @param int $generation
     * @return string
     * @throws JsonException
     */
    public function getKey(AnalyticsFilterForm $form, int $generation): string
    {
        $authorId = $form->authorId === null ? null : (int) $form->authorId;
        $scope = $authorId === null ? 'all' : "user:{$authorId}";
        $filters = [
            'authorId' => $authorId,
            'createdFrom' => $form->createdFrom,
            'createdTo' => $form->createdTo,
        ];
        $hash = hash('sha256', json_encode($filters, JSON_THROW_ON_ERROR));

        return "taskpulse:analytics:tasks:{$scope}:{$generation}:{$hash}";
    }
}
