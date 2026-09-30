<?php

declare(strict_types=1);

namespace tests\unit;

use app\components\DiagnosticData;
use PHPUnit\Framework\TestCase;
use yii\web\Request;

final class DiagnosticDataTest extends TestCase
{
    public function testBodyKeepsShapeAndSafeFlagsOnly(): void
    {
        $body = DiagnosticData::body([
            'title' => 'Секретное имя',
            'description' => 'Пароль 12345',
            'authorId' => 42,
            'completed' => true,
            'password' => 'credential-marker',
        ]);

        self::assertSame(true, $body['completed']);
        self::assertSame(['type' => 'int', 'length' => null], $body['authorId']);
        self::assertSame(1, $body['unknownFieldCount']);
        self::assertStringNotContainsString('Секретное имя', json_encode($body, JSON_UNESCAPED_UNICODE));
        self::assertStringNotContainsString('Пароль 12345', json_encode($body, JSON_UNESCAPED_UNICODE));
        self::assertStringNotContainsString('credential-marker', json_encode($body, JSON_UNESCAPED_UNICODE));
    }

    public function testRequestDoesNotIncludeQueryOrRawBody(): void
    {
        $originalMethod = $_SERVER['REQUEST_METHOD'] ?? null;
        $_SERVER['REQUEST_METHOD'] = 'POST';

        try {
            $request = new Request();
            $request->getHeaders()->set('Content-Type', 'application/json');
            $request->getHeaders()->set('Content-Length', '120');
            $request->setQueryParams(['token' => 'query-secret']);
            $request->setBodyParams(['title' => 'body-secret', 'completed' => false]);

            $data = DiagnosticData::request($request, 'task/task/create');

            self::assertSame('task/task/create', $data['route']);
            self::assertSame(false, $data['data']['completed']);
            self::assertStringNotContainsString('query-secret', json_encode($data, JSON_UNESCAPED_UNICODE));
            self::assertStringNotContainsString('body-secret', json_encode($data, JSON_UNESCAPED_UNICODE));
        } finally {
            if ($originalMethod === null) {
                unset($_SERVER['REQUEST_METHOD']);
            } else {
                $_SERVER['REQUEST_METHOD'] = $originalMethod;
            }
        }
    }

    public function testLogContextDropsUnknownValues(): void
    {
        $context = DiagnosticData::logContext([
            'event' => 'task_event_requeued',
            'attempt' => 2,
            'destination' => 'retry',
            'password' => 'secret-value',
        ]);

        self::assertSame([
            'event' => 'task_event_requeued',
            'attempt' => 2,
            'destination' => 'retry',
        ], $context);
    }
}
