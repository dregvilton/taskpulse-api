<?php

declare(strict_types=1);

namespace tests\api;

use JsonException;

final class UserApiTest extends ApiTestCase
{
    /**
     * @throws JsonException
     */
    public function testCrudAndSoftDelete(): void
    {
        $created = $this->registerUser('Иван Петров', '+79991234567');

        self::assertSame(201, $created['status']);
        self::assertSame('/users/1', $created['headers']['location']);
        self::assertSame(1, $created['body']['id']);
        self::assertSame('Иван Петров', $created['body']['fullName']);
        self::assertArrayNotHasKey('password', $created['body']);
        self::assertArrayNotHasKey('password_hash', $created['body']);
        self::assertIsString($created['body']['createdAt']);

        $view = $this->request('GET', '/users/1');
        self::assertSame(200, $view['status']);
        self::assertSame('+79991234567', $view['body']['phone']);

        $updated = $this->request('PATCH', '/users/1', [
            'fullName' => 'Пётр Иванов',
            'phone' => null,
        ]);
        self::assertSame(200, $updated['status']);
        self::assertSame('Пётр Иванов', $updated['body']['fullName']);
        self::assertNull($updated['body']['phone']);

        $deleted = $this->request('DELETE', '/users/1');
        self::assertSame(204, $deleted['status']);

        $missing = $this->request('GET', '/users/1');
        self::assertSame(401, $missing['status']);

        $deletedAt = $this->db
            ->createCommand('SELECT deleted_at FROM users WHERE id = 1')
            ->queryScalar();
        self::assertIsString($deletedAt);
    }

    /**
     * @throws JsonException
     */
    public function testListContainsOnlyCurrentUser(): void
    {
        foreach (['Первый пользователь', 'Второй пользователь', 'Третий пользователь'] as $name) {
            $response = $this->registerUser($name);
            self::assertSame(201, $response['status']);
        }

        $response = $this->request('GET', '/users?page=1&perPage=2');

        self::assertSame(200, $response['status']);
        self::assertCount(1, $response['body']['items']);
        self::assertSame(3, $response['body']['items'][0]['id']);
        self::assertSame(1, $response['body']['_meta']['totalCount']);
        self::assertSame(1, $response['body']['_meta']['pageCount']);
    }

    /**
     * @throws JsonException
     */
    public function testValidationError(): void
    {
        $response = $this->request('POST', '/users', [
            'fullName' => 'И',
            'phone' => '89991234567',
            'email' => 'invalid@example.test',
            'password' => bin2hex(random_bytes(16)),
        ]);

        self::assertSame(422, $response['status']);
        self::assertSame('fullName', $response['body'][0]['field']);
        self::assertSame('Имя должно содержать не менее 3 символов.', $response['body'][0]['message']);
    }

    /**
     * @throws JsonException
     */
    public function testPaginationValidationError(): void
    {
        $this->registerUser('Иван Петров');
        $response = $this->request('GET', '/users?page=wrong&perPage=101');

        self::assertSame(422, $response['status']);
        self::assertSame('page', $response['body'][0]['field']);
        self::assertSame(
            'Номер страницы должен быть целым числом.',
            $response['body'][0]['message'],
        );
    }

    /**
     * @throws JsonException
     */
    public function testUpdateRequiresChanges(): void
    {
        $created = $this->registerUser('Иван Петров');
        self::assertSame(201, $created['status']);

        $response = $this->request('PATCH', '/users/1', []);

        self::assertSame(422, $response['status']);
        self::assertSame('fullName', $response['body'][0]['field']);
        self::assertSame('Не переданы данные для обновления.', $response['body'][0]['message']);
    }

}
