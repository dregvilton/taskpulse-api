<?php

declare(strict_types=1);

namespace tests\api;

use JsonException;

final class AuthApiTest extends ApiTestCase
{
    /**
     * @throws JsonException
     */
    public function testLoginLogoutAndHashedCredentials(): void
    {
        $email = bin2hex(random_bytes(8)) . '@example.test';
        $password = bin2hex(random_bytes(16));
        $created = $this->request('POST', '/users', [
            'fullName' => 'Иван Петров',
            'email' => $email,
            'password' => $password,
        ]);
        self::assertSame(201, $created['status']);
        self::assertArrayNotHasKey('password', $created['body']);
        self::assertArrayNotHasKey('password_hash', $created['body']);

        $hash = $this->db->createCommand('SELECT password_hash FROM users WHERE id = 1')->queryScalar();
        self::assertIsString($hash);
        self::assertNotSame($password, $hash);
        self::assertTrue(password_verify($password, $hash));

        $wrong = $this->request('POST', '/auth/login', [
            'email' => $email,
            'password' => 'wrong-password',
        ]);
        self::assertSame(401, $wrong['status']);

        $login = $this->request('POST', '/auth/login', [
            'email' => strtoupper($email),
            'password' => $password,
        ]);
        self::assertSame(200, $login['status']);
        self::assertSame('Bearer', $login['body']['tokenType']);
        self::assertSame(1, $login['body']['userId']);
        self::assertIsString($login['body']['accessToken']);
        $token = $login['body']['accessToken'];
        self::assertSame(64, strlen($token));
        self::assertSame(
            hash('sha256', $token),
            $this->db->createCommand('SELECT token_hash FROM auth_tokens')->queryScalar(),
        );

        $secondLogin = $this->request('POST', '/auth/login', [
            'email' => $email,
            'password' => $password,
        ]);
        self::assertSame(200, $secondLogin['status']);
        $secondToken = $secondLogin['body']['accessToken'];
        self::assertIsString($secondToken);
        self::assertNotSame($token, $secondToken);

        $this->accessToken = $token;
        self::assertSame(200, $this->request('GET', '/users/1')['status']);
        self::assertSame(204, $this->request('POST', '/auth/logout', null, [
            'Authorization' => "Bearer   {$token}",
        ])['status']);
        self::assertSame(401, $this->request('GET', '/users/1')['status']);

        $this->accessToken = $secondToken;
        self::assertSame(200, $this->request('GET', '/users/1')['status']);
    }

    /**
     * @throws JsonException
     */
    public function testProtectedRoutesRejectMissingAndExpiredTokens(): void
    {
        $created = $this->registerUser('Иван Петров');
        self::assertSame(201, $created['status']);
        $token = $this->accessToken;

        $this->accessToken = null;
        foreach (['/users', '/users/1', '/tasks', '/analytics/tasks'] as $path) {
            $unauthorized = $this->request('GET', $path);
            self::assertSame(401, $unauthorized['status']);
            self::assertSame('Необходима авторизация.', $unauthorized['body']['message']);
        }
        self::assertSame(401, $this->request('POST', '/tasks', ['title' => 'Без входа'])['status']);
        self::assertSame(200, $this->request('GET', '/health')['status']);

        $this->accessToken = $token;
        $this->db->createCommand()->update('auth_tokens', [
            'expires_at' => '2000-01-01 00:00:00',
        ])->execute();
        self::assertSame(401, $this->request('GET', '/tasks')['status']);
    }

    /**
     * @throws JsonException
     */
    public function testMalformedCredentialsAreRejectedAsValidationErrors(): void
    {
        $register = $this->request('POST', '/users', [
            'fullName' => 'Иван Петров',
            'email' => ['not-an-email'],
            'password' => bin2hex(random_bytes(16)),
        ]);
        self::assertSame(422, $register['status']);

        $login = $this->request('POST', '/auth/login', [
            'email' => ['not-an-email'],
            'password' => 'irrelevant',
        ]);
        self::assertSame(422, $login['status']);
    }

    /**
     * @throws JsonException
     */
    public function testForeignDataIsInaccessibleAndIdempotencyKeysAreScoped(): void
    {
        $first = $this->registerUser('Иван Петров');
        self::assertSame(201, $first['status']);
        $firstToken = $this->accessToken;
        $firstTask = $this->request('POST', '/tasks', [
            'authorId' => 999,
            'title' => 'Задача Ивана',
        ], ['Idempotency-Key' => 'shared-key']);
        self::assertSame(201, $firstTask['status']);
        self::assertSame(1, $firstTask['body']['authorId']);

        $second = $this->registerUser('Анна Смирнова');
        self::assertSame(201, $second['status']);
        $secondTask = $this->request('POST', '/tasks', [
            'authorId' => 1,
            'title' => 'Задача Анны',
        ], ['Idempotency-Key' => 'shared-key']);
        self::assertSame(201, $secondTask['status']);
        self::assertSame(2, $secondTask['body']['authorId']);
        self::assertSame(2, (int) $this->db->createCommand('SELECT COUNT(*) FROM idempotency_keys')->queryScalar());

        self::assertSame(403, $this->request('GET', '/users/1')['status']);
        self::assertSame(403, $this->request('PATCH', '/users/1', ['fullName' => 'Чужое имя'])['status']);
        self::assertSame(403, $this->request('GET', '/users/1/tasks')['status']);
        self::assertSame(404, $this->request('GET', '/tasks/1')['status']);
        self::assertSame(404, $this->request('PATCH', '/tasks/1', ['completed' => true])['status']);
        self::assertSame(404, $this->request('DELETE', '/tasks/1')['status']);

        self::assertSame(403, $this->request('GET', '/tasks?authorId=1')['status']);
        self::assertSame(403, $this->request('GET', '/analytics/tasks?authorId=1')['status']);

        $list = $this->request('GET', '/tasks');
        self::assertSame(200, $list['status']);
        self::assertCount(1, $list['body']['items']);
        self::assertSame(2, $list['body']['items'][0]['authorId']);
        $analytics = $this->request('GET', '/analytics/tasks');
        self::assertSame(200, $analytics['status']);
        self::assertSame(1, $analytics['body']['totalCreated']);

        $this->accessToken = $firstToken;
        self::assertSame(200, $this->request('GET', '/tasks/1')['status']);
        self::assertSame(404, $this->request('GET', '/tasks/2')['status']);
    }
}
