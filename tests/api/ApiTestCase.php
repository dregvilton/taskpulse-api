<?php

declare(strict_types=1);

namespace tests\api;

use JsonException;
use PHPUnit\Framework\TestCase;
use Yii;
use yii\db\Connection;

/**
 * Базовый класс API-тестов.
 */
abstract class ApiTestCase extends TestCase
{
    protected Connection $db;
    protected ?string $accessToken = null;

    protected function setUp(): void
    {
        /** @var Connection $db */
        $db = Yii::$app->get('db');
        $this->db = $db;

        $this->db->createCommand(
            'TRUNCATE TABLE auth_tokens, processed_task_events, task_events, idempotency_keys, tasks, users RESTART IDENTITY CASCADE',
        )->execute();
        $this->accessToken = null;
    }

    /**
     * Выдать тестовому пользователю учётные данные и войти через API.
     *
     * @param int $userId
     * @return string
     * @throws JsonException
     */
    protected function authenticateAs(int $userId): string
    {
        $email = "user{$userId}@example.test";
        $password = bin2hex(random_bytes(16));
        $this->db->createCommand()->update('users', [
            'email' => $email,
            'password_hash' => Yii::$app->security->generatePasswordHash($password),
        ], ['id' => $userId])->execute();

        $this->accessToken = null;
        $response = $this->request('POST', '/auth/login', [
            'email' => $email,
            'password' => $password,
        ]);
        self::assertSame(200, $response['status']);
        self::assertIsString($response['body']['accessToken']);
        $this->accessToken = $response['body']['accessToken'];

        return $this->accessToken;
    }

    /**
     * Зарегистрировать пользователя и войти через API.
     *
     * @param string $fullName
     * @param string|null $phone
     * @return array{status: int, headers: array<string, string>, body: array<int|string, mixed>}
     * @throws JsonException
     */
    protected function registerUser(string $fullName, ?string $phone = null): array
    {
        $email = bin2hex(random_bytes(8)) . '@example.test';
        $password = bin2hex(random_bytes(16));
        $created = $this->request('POST', '/users', [
            'fullName' => $fullName,
            'phone' => $phone,
            'email' => $email,
            'password' => $password,
        ]);
        if ($created['status'] !== 201) {
            return $created;
        }

        $this->accessToken = null;
        $login = $this->request('POST', '/auth/login', [
            'email' => $email,
            'password' => $password,
        ]);
        self::assertSame(200, $login['status']);
        self::assertIsString($login['body']['accessToken']);
        $this->accessToken = $login['body']['accessToken'];

        return $created;
    }

    /**
     * Выполнить HTTP-запрос к API.
     *
     * @param string $method
     * @param string $path
     * @param array<string, mixed>|null $body
     * @param array<string, string> $extraHeaders
     * @return array{
     *     status: int,
     *     headers: array<string, string>,
     *     body: array<int|string, mixed>
     * }
     * @throws JsonException
     */
    protected function request(string $method, string $path, ?array $body = null, array $extraHeaders = []): array
    {
        $headers = ['Accept: application/json'];
        if ($this->accessToken !== null && !array_key_exists('Authorization', $extraHeaders)) {
            $headers[] = 'Authorization: Bearer ' . $this->accessToken;
        }
        foreach ($extraHeaders as $name => $value) {
            $headers[] = "{$name}: {$value}";
        }
        $options = [
            'method' => $method,
            'ignore_errors' => true,
        ];

        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            $options['content'] = json_encode($body, JSON_THROW_ON_ERROR);
        }
        $options['header'] = implode("\r\n", $headers);

        $response = file_get_contents(
            ($_ENV['API_BASE_URL'] ?? 'http://nginx') . $path,
            false,
            stream_context_create(['http' => $options]),
        );
        self::assertNotFalse($response);

        /** @var list<string> $http_response_header */
        $statusLine = $http_response_header[0] ?? '';
        preg_match('/\s(\d{3})\s/', $statusLine, $matches);
        self::assertArrayHasKey(1, $matches);

        $responseHeaders = [];
        foreach (array_slice($http_response_header, 1) as $header) {
            if (!str_contains($header, ':')) {
                continue;
            }

            [$name, $value] = explode(':', $header, 2);
            $responseHeaders[strtolower($name)] = trim($value);
        }

        $decoded = $response === '' ? [] : json_decode($response, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return [
            'status' => (int) $matches[1],
            'headers' => $responseHeaders,
            'body' => $decoded,
        ];
    }
}
