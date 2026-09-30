<?php

declare(strict_types=1);

namespace tests\api;

use JsonException;
use yii\db\Exception;

final class TaskIdempotencyApiTest extends ApiTestCase
{
    /**
     * @throws JsonException|Exception
     */
    public function testReplayReturnsOriginalResponseAfterTaskAndAuthorChange(): void
    {
        $this->createUser();
        $body = ['authorId' => 1, 'title' => 'Исходная задача'];
        $headers = ['Idempotency-Key' => 'create-task-1'];

        $created = $this->request('POST', '/tasks', $body, $headers);
        self::assertSame(201, $created['status']);
        self::assertSame('/tasks/1', $created['headers']['location']);

        $this->request('PATCH', '/tasks/1', ['title' => 'Изменённая задача']);
        $this->request('DELETE', '/users/1');

        $replayed = $this->request('POST', '/tasks', [
            'title' => 'Исходная задача',
            'authorId' => 1,
        ], $headers);
        self::assertSame($created['status'], $replayed['status']);
        self::assertSame($created['headers']['location'], $replayed['headers']['location']);
        self::assertSame($created['body'], $replayed['body']);
        self::assertNotSame($created['headers']['x-request-id'], $replayed['headers']['x-request-id']);
        self::assertSame(1, (int) $this->db->createCommand('SELECT COUNT(*) FROM tasks')->queryScalar());
        self::assertSame(1, (int) $this->db->createCommand('SELECT COUNT(*) FROM idempotency_keys')->queryScalar());
    }

    /**
     * @throws JsonException|Exception
     */
    public function testKeyCannotBeReusedForAnotherRequest(): void
    {
        $this->createUser();
        $headers = ['Idempotency-Key' => 'same-key'];

        $created = $this->request('POST', '/tasks', ['authorId' => 1, 'title' => 'Первая'], $headers);
        $conflict = $this->request('POST', '/tasks', ['authorId' => 1, 'title' => 'Другая'], $headers);
        $unknownFieldConflict = $this->request('POST', '/tasks', [
            'authorId' => 1,
            'title' => 'Первая',
            'extra' => true,
        ], $headers);

        self::assertSame(201, $created['status']);
        self::assertSame(409, $conflict['status']);
        self::assertSame(409, $unknownFieldConflict['status']);
        self::assertSame('Ключ идемпотентности уже использован для другого запроса.', $conflict['body']['message']);
        self::assertSame(1, (int) $this->db->createCommand('SELECT COUNT(*) FROM tasks')->queryScalar());
    }

    /**
     * @throws JsonException|Exception
     */
    public function testInvalidKeyIsRejected(): void
    {
        $this->createUser();
        $body = ['authorId' => 1, 'title' => 'Новая задача'];

        $empty = $this->request('POST', '/tasks', $body, ['Idempotency-Key' => '']);
        $long = $this->request('POST', '/tasks', $body, ['Idempotency-Key' => str_repeat('x', 256)]);

        self::assertSame(422, $empty['status']);
        self::assertSame('idempotencyKey', $empty['body'][0]['field']);
        self::assertSame(422, $long['status']);
        self::assertSame('idempotencyKey', $long['body'][0]['field']);
        self::assertSame(0, (int) $this->db->createCommand('SELECT COUNT(*) FROM tasks')->queryScalar());
    }

    /**
     * @throws JsonException|Exception
     */
    public function testFailedCreationDoesNotReserveKey(): void
    {
        $this->createUser();
        $body = ['authorId' => 1, 'title' => 'Откат'];
        $headers = ['Idempotency-Key' => 'retry-after-failure'];

        $this->db->createCommand(
            "ALTER TABLE tasks ADD CONSTRAINT \"chk-test-task-rejection\" CHECK (title <> 'Откат')",
        )->execute();

        try {
            $failed = $this->request('POST', '/tasks', $body, $headers);
            self::assertSame(500, $failed['status']);
            self::assertSame(0, (int) $this->db->createCommand('SELECT COUNT(*) FROM tasks')->queryScalar());
            self::assertSame(0, (int) $this->db->createCommand('SELECT COUNT(*) FROM idempotency_keys')->queryScalar());
        } finally {
            $this->db->createCommand('ALTER TABLE tasks DROP CONSTRAINT "chk-test-task-rejection"')->execute();
        }

        $retry = $this->request('POST', '/tasks', $body, $headers);
        self::assertSame(201, $retry['status']);
        self::assertSame(1, (int) $this->db->createCommand('SELECT COUNT(*) FROM tasks')->queryScalar());
    }

    /**
     * @throws JsonException|Exception
     */
    public function testConcurrentRequestsCreateOnlyOneTask(): void
    {
        $this->createUser();
        $transaction = $this->db->beginTransaction();
        $first = null;
        $second = null;

        try {
            $this->db->createCommand('LOCK TABLE idempotency_keys IN ACCESS EXCLUSIVE MODE')->execute();
            $first = $this->openRequest('concurrent-key');
            $second = $this->openRequest('concurrent-key');
            usleep(300_000);
            $transaction->commit();

            $firstResponse = $this->readResponse($first);
            $secondResponse = $this->readResponse($second);
            self::assertSame(201, $firstResponse['status']);
            self::assertSame($firstResponse, $secondResponse);
            self::assertSame(1, (int) $this->db->createCommand('SELECT COUNT(*) FROM tasks')->queryScalar());
            self::assertSame(1, (int) $this->db->createCommand('SELECT COUNT(*) FROM idempotency_keys')->queryScalar());
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
     * @return void
     * @throws Exception
     */
    private function createUser(): void
    {
        $this->db->createCommand()->insert('users', ['full_name' => 'Иван Петров'])->execute();
    }

    /**
     * @param string $key
     * @return resource
     * @throws JsonException
     */
    private function openRequest(string $key)
    {
        $baseUrl = $_ENV['API_BASE_URL'] ?? 'http://nginx';
        $host = parse_url($baseUrl, PHP_URL_HOST);
        self::assertIsString($host);
        $port = parse_url($baseUrl, PHP_URL_PORT) ?? 80;
        self::assertIsInt($port);

        $socket = stream_socket_client("tcp://{$host}:{$port}", $errorCode, $errorMessage, 5);
        self::assertNotFalse($socket, $errorMessage);
        stream_set_timeout($socket, 10);

        $body = json_encode(['authorId' => 1, 'title' => 'Параллельная задача'], JSON_THROW_ON_ERROR);
        $request = "POST /tasks HTTP/1.0\r\n"
            . "Host: {$host}\r\n"
            . "Accept: application/json\r\n"
            . "Content-Type: application/json\r\n"
            . "Idempotency-Key: {$key}\r\n"
            . 'Content-Length: ' . strlen($body) . "\r\n"
            . "Connection: close\r\n\r\n"
            . $body;
        self::assertSame(strlen($request), fwrite($socket, $request));
        return $socket;
    }

    /**
     * @param resource $socket
     * @return array{status: int, location: string, body: array<string, mixed>}
     * @throws JsonException
     */
    private function readResponse($socket): array
    {
        $response = stream_get_contents($socket);
        self::assertNotFalse($response);
        self::assertFalse(stream_get_meta_data($socket)['timed_out']);
        fclose($socket);

        self::assertStringContainsString("\r\n\r\n", $response, $response);
        [$rawHeaders, $rawBody] = explode("\r\n\r\n", $response, 2);
        preg_match('/^HTTP\/\d\.\d (\d{3})/m', $rawHeaders, $status);
        preg_match('/^Location:\s*(.+)$/mi', $rawHeaders, $location);
        self::assertArrayHasKey(1, $status);
        self::assertArrayHasKey(1, $location);
        $body = json_decode($rawBody, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($body);

        return [
            'status' => (int) $status[1],
            'location' => trim($location[1]),
            'body' => $body,
        ];
    }
}
