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
        $utcDateTimePattern = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/';
        self::assertMatchesRegularExpression($utcDateTimePattern, $created['body']['createdAt']);
        self::assertMatchesRegularExpression($utcDateTimePattern, $created['body']['updatedAt']);

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

    /**
     * @throws JsonException
     */
    public function testScalarJsonBodyIsRejected(): void
    {
        foreach (['42', '"text"', 'true', 'null', '[]', '[{"fullName":"Иван Петров"}]'] as $body) {
            $response = $this->request('POST', '/users', $body);
            self::assertSame(400, $response['status'], $body);
        }

        self::assertSame(422, $this->request('POST', '/users', '{"0":"value"}')['status']);

        self::assertSame(0, (int) $this->db->createCommand('SELECT COUNT(*) FROM users')->queryScalar());
    }

    /**
     * @throws JsonException
     */
    public function testConcurrentRegistrationWithSameEmail(): void
    {
        $transaction = $this->db->beginTransaction();
        $first = null;
        $second = null;

        try {
            $this->db->createCommand('LOCK TABLE users IN SHARE MODE')->execute();
            $first = $this->openRegistrationRequest();
            $second = $this->openRegistrationRequest();
            $waiting = 0;
            $deadline = microtime(true) + 5;
            do {
                $waiting = (int) $this->db->createCommand(
                    "SELECT COUNT(*) FROM pg_locks WHERE relation = 'users'::regclass "
                    . "AND mode = 'RowExclusiveLock' AND NOT granted",
                )->queryScalar();
                if ($waiting === 2) {
                    break;
                }
                usleep(50_000);
            } while (microtime(true) < $deadline);
            self::assertSame(2, $waiting, 'Оба запроса должны дойти до вставки после проверки формы.');
            $transaction->commit();

            $responses = [$this->readRegistrationResponse($first), $this->readRegistrationResponse($second)];
            $statuses = array_column($responses, 'status');
            sort($statuses);

            self::assertSame([201, 422], $statuses);
            foreach ($responses as $response) {
                if ($response['status'] === 422) {
                    self::assertSame('email', $response['body'][0]['field']);
                    self::assertSame('Почта уже используется.', $response['body'][0]['message']);
                }
            }
            self::assertSame(1, (int) $this->db->createCommand('SELECT COUNT(*) FROM users')->queryScalar());
        } finally {
            if ($transaction->isActive) {
                $transaction->rollBack();
            }
            if (is_resource($first)) {
                fclose($first);
            }
            if (is_resource($second)) {
                fclose($second);
            }
        }
    }

    /**
     * @return resource
     * @throws JsonException
     */
    private function openRegistrationRequest()
    {
        $baseUrl = $_ENV['API_BASE_URL'] ?? 'http://nginx';
        $host = parse_url($baseUrl, PHP_URL_HOST);
        self::assertIsString($host);
        $port = parse_url($baseUrl, PHP_URL_PORT) ?? 80;
        self::assertIsInt($port);

        $socket = stream_socket_client("tcp://{$host}:{$port}", $errorCode, $errorMessage, 5);
        self::assertNotFalse($socket, $errorMessage);
        stream_set_timeout($socket, 10);

        $body = json_encode([
            'fullName' => 'Иван Петров',
            'email' => 'concurrent@example.test',
            'password' => 'long-test-password-123',
        ], JSON_THROW_ON_ERROR);
        $request = "POST /users HTTP/1.0\r\n"
            . "Host: {$host}\r\n"
            . "Accept: application/json\r\n"
            . "Content-Type: application/json\r\n"
            . 'Content-Length: ' . strlen($body) . "\r\n"
            . "Connection: close\r\n\r\n"
            . $body;
        self::assertSame(strlen($request), fwrite($socket, $request));

        return $socket;
    }

    /**
     * @param resource $socket
     * @return array{status: int, body: array<int|string, mixed>}
     * @throws JsonException
     */
    private function readRegistrationResponse($socket): array
    {
        $response = stream_get_contents($socket);
        self::assertNotFalse($response);
        self::assertFalse(stream_get_meta_data($socket)['timed_out']);
        fclose($socket);

        self::assertStringContainsString("\r\n\r\n", $response);
        [$rawHeaders, $rawBody] = explode("\r\n\r\n", $response, 2);
        preg_match('/^HTTP\/\d\.\d (\d{3})/m', $rawHeaders, $status);
        self::assertArrayHasKey(1, $status);
        $body = json_decode($rawBody, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($body);

        return ['status' => (int) $status[1], 'body' => $body];
    }
}
