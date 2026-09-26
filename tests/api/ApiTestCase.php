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

    protected function setUp(): void
    {
        /** @var Connection $db */
        $db = Yii::$app->get('db');
        $this->db = $db;

        $this->db->createCommand('TRUNCATE TABLE idempotency_keys, tasks, users RESTART IDENTITY CASCADE')->execute();
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
