<?php

declare(strict_types=1);

namespace tests\api;

use app\services\AnalyticsCache;
use JsonException;
use Yii;
use yii\redis\Connection;

final class AnalyticsApiTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        /** @var Connection $redis */
        $redis = Yii::$app->get('redis');
        (new AnalyticsCache($redis))->invalidate();
    }

    /**
     * @throws JsonException
     */
    public function testMetricsAndFilters(): void
    {
        $empty = $this->request('GET', '/analytics/tasks');
        self::assertSame(200, $empty['status']);
        self::assertSame([
            'totalCreated' => 0,
            'totalCompleted' => 0,
            'completionPercent' => 0,
            'avgCompletionTimeSeconds' => null,
        ], $empty['body']);

        $this->createUsers();
        $this->createTask(1, 'Первая задача', true);
        $this->createTask(1, 'Вторая задача', true);
        $this->createTask(1, 'Третья задача', false);
        $this->createTask(2, 'Четвёртая задача', true);

        $this->setTaskDates(1, '2026-09-01 10:00:00', '2026-09-01 11:00:00');
        $this->setTaskDates(2, '2026-09-02 10:00:00', '2026-09-02 12:00:00');
        $this->setTaskDates(3, '2026-09-03 10:00:00', null);
        $this->setTaskDates(4, '2026-09-02 10:00:00', '2026-09-02 10:30:00');

        $all = $this->request('GET', '/analytics/tasks');
        self::assertSame(200, $all['status']);
        self::assertSame([
            'totalCreated' => 4,
            'totalCompleted' => 3,
            'completionPercent' => 75,
            'avgCompletionTimeSeconds' => 4200,
        ], $all['body']);

        $filtered = $this->request(
            'GET',
            '/analytics/tasks?authorId=1'
            . '&createdFrom=2026-09-01T00%3A00%3A00%2B00%3A00'
            . '&createdTo=2026-09-02T23%3A59%3A59%2B00%3A00',
        );
        self::assertSame(200, $filtered['status']);
        self::assertSame([
            'totalCreated' => 2,
            'totalCompleted' => 2,
            'completionPercent' => 100,
            'avgCompletionTimeSeconds' => 5400,
        ], $filtered['body']);
    }

    /**
     * @throws JsonException
     */
    public function testRedisCacheAndInvalidation(): void
    {
        $this->createUsers();
        $this->createTask(1, 'Первая задача', false);

        $initial = $this->request('GET', '/analytics/tasks?authorId=1');
        self::assertSame(200, $initial['status']);
        self::assertSame(1, $initial['body']['totalCreated']);
        self::assertSame(0, $initial['body']['totalCompleted']);

        $this->db->createCommand()->insert('tasks', [
            'author_id' => 1,
            'title' => 'Вторая задача',
        ])->execute();

        $cached = $this->request('GET', '/analytics/tasks?authorId=1');
        self::assertSame(1, $cached['body']['totalCreated']);

        $completed = $this->request('PATCH', '/tasks/1', ['completed' => true]);
        self::assertSame(200, $completed['status']);

        $updated = $this->request('GET', '/analytics/tasks?authorId=1');
        self::assertSame(2, $updated['body']['totalCreated']);
        self::assertSame(1, $updated['body']['totalCompleted']);
        self::assertSame(50, $updated['body']['completionPercent']);

        $deleted = $this->request('DELETE', '/tasks/2');
        self::assertSame(204, $deleted['status']);

        $afterDelete = $this->request('GET', '/analytics/tasks?authorId=1');
        self::assertSame(1, $afterDelete['body']['totalCreated']);
        self::assertSame(1, $afterDelete['body']['totalCompleted']);
    }

    /**
     * @throws JsonException
     */
    public function testInvalidFilters(): void
    {
        $invalid = $this->request('GET', '/analytics/tasks?authorId=wrong&createdFrom=not-a-date');
        self::assertSame(422, $invalid['status']);
        self::assertSame('authorId', $invalid['body'][0]['field']);
    }

    private function createUsers(): void
    {
        $this->db->createCommand()->batchInsert(
            'users',
            ['full_name'],
            [
                ['Иван Петров'],
                ['Анна Смирнова'],
            ],
        )->execute();
    }

    /**
     * @throws JsonException
     */
    private function createTask(int $authorId, string $title, bool $completed): void
    {
        $response = $this->request('POST', '/tasks', [
            'authorId' => $authorId,
            'title' => $title,
            'completed' => $completed,
        ]);

        self::assertSame(201, $response['status']);
    }

    private function setTaskDates(int $id, string $createdAt, ?string $completedAt): void
    {
        $this->db->createCommand()->update('tasks', [
            'created_at' => $createdAt,
            'completed_at' => $completedAt,
        ], ['id' => $id])->execute();
    }
}
