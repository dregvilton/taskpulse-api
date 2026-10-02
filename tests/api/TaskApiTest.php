<?php

declare(strict_types=1);

namespace tests\api;

use JsonException;

final class TaskApiTest extends ApiTestCase
{
    /**
     * @throws JsonException
     */
    public function testCrudAndCompletionState(): void
    {
        $this->createUsers();

        $created = $this->request('POST', '/tasks', [
            'authorId' => 1,
            'title' => 'Подготовить отчёт',
            'description' => 'Собрать данные за неделю',
        ]);

        self::assertSame(201, $created['status']);
        self::assertSame('/tasks/1', $created['headers']['location']);
        self::assertSame(1, $created['body']['id']);
        self::assertSame(1, $created['body']['authorId']);
        self::assertFalse($created['body']['completed']);
        self::assertNull($created['body']['completedAt']);
        $utcDateTimePattern = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/';
        self::assertMatchesRegularExpression($utcDateTimePattern, $created['body']['createdAt']);
        self::assertMatchesRegularExpression($utcDateTimePattern, $created['body']['updatedAt']);

        $view = $this->request('GET', '/tasks/1');
        self::assertSame(200, $view['status']);
        self::assertSame('Подготовить отчёт', $view['body']['title']);

        $completed = $this->request('PATCH', '/tasks/1', ['completed' => true]);
        self::assertSame(200, $completed['status']);
        self::assertTrue($completed['body']['completed']);
        self::assertIsString($completed['body']['completedAt']);
        self::assertMatchesRegularExpression($utcDateTimePattern, $completed['body']['completedAt']);
        $completedAt = $completed['body']['completedAt'];

        $unchangedState = $this->request('PATCH', '/tasks/1', ['completed' => true]);
        self::assertSame(200, $unchangedState['status']);
        self::assertSame($completedAt, $unchangedState['body']['completedAt']);

        $updated = $this->request('PATCH', '/tasks/1', [
            'title' => 'Подготовить итоговый отчёт',
            'description' => null,
        ]);
        self::assertSame(200, $updated['status']);
        self::assertSame('Подготовить итоговый отчёт', $updated['body']['title']);
        self::assertNull($updated['body']['description']);
        self::assertIsString($updated['body']['completedAt']);

        $reopened = $this->request('PATCH', '/tasks/1', ['completed' => false]);
        self::assertSame(200, $reopened['status']);
        self::assertFalse($reopened['body']['completed']);
        self::assertNull($reopened['body']['completedAt']);

        $deleted = $this->request('DELETE', '/tasks/1');
        self::assertSame(204, $deleted['status']);

        $missing = $this->request('GET', '/tasks/1');
        self::assertSame(404, $missing['status']);

        $deletedAt = $this->db
            ->createCommand('SELECT deleted_at FROM tasks WHERE id = 1')
            ->queryScalar();
        self::assertIsString($deletedAt);
    }

    /**
     * @throws JsonException
     */
    public function testFiltersSortingAndPagination(): void
    {
        $this->createUsers();
        $this->createTask(1, 'Альфа', true);
        $this->createTask(1, 'Бета', true);
        $this->createTask(1, 'Гамма', false);
        $this->createTask(2, 'Дельта', true);

        $this->db->createCommand(
            <<<'SQL'
                UPDATE tasks
                SET
                    created_at = CASE id
                        WHEN 1 THEN TIMESTAMP '2026-09-01 10:00:00'
                        WHEN 2 THEN TIMESTAMP '2026-09-02 10:00:00'
                        WHEN 3 THEN TIMESTAMP '2026-09-03 10:00:00'
                        WHEN 4 THEN TIMESTAMP '2026-09-04 10:00:00'
                    END,
                    completed_at = CASE id
                        WHEN 1 THEN TIMESTAMP '2026-09-01 11:00:00'
                        WHEN 2 THEN TIMESTAMP '2026-09-02 11:00:00'
                        WHEN 4 THEN TIMESTAMP '2026-09-04 11:00:00'
                    END
                SQL,
        )->execute();

        $response = $this->request(
            'GET',
            '/tasks?authorId=1&completed=true'
            . '&createdFrom=2026-09-01T00%3A00%3A00%2B00%3A00'
            . '&createdTo=2026-09-02T23%3A59%3A59%2B00%3A00'
            . '&completedFrom=2026-09-01T00%3A00%3A00%2B00%3A00'
            . '&completedTo=2026-09-02T23%3A59%3A59%2B00%3A00'
            . '&sort=-title&page=1&perPage=1',
        );

        self::assertSame(200, $response['status']);
        self::assertCount(1, $response['body']['items']);
        self::assertSame('Бета', $response['body']['items'][0]['title']);
        self::assertSame('2026-09-02T10:00:00+00:00', $response['body']['items'][0]['createdAt']);
        self::assertSame('2026-09-02T11:00:00+00:00', $response['body']['items'][0]['completedAt']);
        self::assertSame(2, $response['body']['_meta']['totalCount']);
        self::assertSame(2, $response['body']['_meta']['pageCount']);

        $openTasks = $this->request('GET', '/tasks?completed=false');
        self::assertSame(200, $openTasks['status']);
        self::assertCount(1, $openTasks['body']['items']);
        self::assertSame('Гамма', $openTasks['body']['items'][0]['title']);
    }

    /**
     * @throws JsonException
     */
    public function testUserTasks(): void
    {
        $this->createUsers();
        $this->createTask(1, 'Первая задача');
        $this->createTask(1, 'Вторая задача');
        $this->createTask(2, 'Чужая задача');

        $response = $this->request('GET', '/users/1/tasks?sort=title');

        self::assertSame(200, $response['status']);
        self::assertCount(2, $response['body']['items']);
        self::assertSame(1, $response['body']['items'][0]['authorId']);
        self::assertSame(1, $response['body']['items'][1]['authorId']);
        self::assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/',
            $response['body']['items'][0]['createdAt'],
        );

        $missing = $this->request('GET', '/users/999/tasks');
        self::assertSame(403, $missing['status']);
    }

    /**
     * @throws JsonException
     */
    public function testValidation(): void
    {
        $this->createUsers();

        $ignoredAuthor = $this->request('POST', '/tasks', [
            'authorId' => 999,
            'title' => 'Новая задача',
        ]);
        self::assertSame(201, $ignoredAuthor['status']);
        self::assertSame(1, $ignoredAuthor['body']['authorId']);

        $created = $this->createTask(1, 'Новая задача');
        self::assertSame(201, $created['status']);

        $emptyUpdate = $this->request('PATCH', '/tasks/1', []);
        self::assertSame(422, $emptyUpdate['status']);
        self::assertSame('Не переданы данные для обновления.', $emptyUpdate['body'][0]['message']);

        $invalidFilter = $this->request('GET', '/tasks?completed=yes&sort=authorId');
        self::assertSame(422, $invalidFilter['status']);
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
        $this->authenticateAs(1);
    }

    /**
     * @return array{
     *     status: int,
     *     headers: array<string, string>,
     *     body: array<int|string, mixed>
     * }
     * @throws JsonException
     */
    private function createTask(int $authorId, string $title, bool $completed = false): array
    {
        $previousToken = $this->accessToken;
        if ($authorId !== 1) {
            $this->authenticateAs($authorId);
        }
        $response = $this->request('POST', '/tasks', [
            'authorId' => $authorId,
            'title' => $title,
            'completed' => $completed,
        ]);
        $this->accessToken = $previousToken;

        self::assertSame(201, $response['status']);

        return $response;
    }
}
