<?php

declare(strict_types=1);

namespace tests\unit;

use app\components\JsonLogTarget;
use app\components\RequestContext;
use JsonException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use yii\log\Logger;

final class JsonLogTargetTest extends TestCase
{
    /**
     * @throws JsonException
     */
    public function testExceptionIsFormattedWithoutMessageOrArguments(): void
    {
        $context = new RequestContext();
        $context->start('request-123');
        $target = new JsonLogTarget(['requestContext' => $context]);

        self::assertSame('php://stderr', $target->logFile);
        self::assertFalse($target->enableRotation);

        $json = $target->formatMessage([
            new RuntimeException('private-value'),
            Logger::LEVEL_ERROR,
            'app\\test',
            time(),
        ]);
        $record = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('request-123', $record['request_id']);
        self::assertSame(RuntimeException::class, $record['exception']['type']);
        self::assertArrayNotHasKey('trace', $record['exception']);
        self::assertStringNotContainsString('private-value', $json);
    }

    /**
     * @throws JsonException
     */
    public function testArrayContextIsFiltered(): void
    {
        $target = new JsonLogTarget();
        $json = $target->formatMessage([
            ['event' => 'worker_started', 'password' => 'secret-value'],
            Logger::LEVEL_INFO,
            'app\\worker',
            time(),
        ]);
        $record = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(['event' => 'worker_started'], $record['context']);
        self::assertStringNotContainsString('secret-value', $json);
    }
}
