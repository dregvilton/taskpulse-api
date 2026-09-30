<?php

declare(strict_types=1);

namespace tests\unit;

use app\components\RequestContext;
use app\components\SentryLogTarget;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sentry\Event;
use Sentry\Options;
use Sentry\Serializer\PayloadSerializer;
use Sentry\Transport\Result;
use Sentry\Transport\ResultStatus;
use Sentry\Transport\TransportInterface;
use yii\log\Logger;
use yii\web\NotFoundHttpException;
use yii\web\Request;

final class SentryLogTargetTest extends TestCase
{
    public function testOnlyErrorsAreSentWithoutSecrets(): void
    {
        $transport = new RecordingSentryTransport();
        $context = new RequestContext();
        $context->start('request-123');
        $request = new Request();
        $request->getHeaders()->set('Content-Type', 'application/json');
        $request->getHeaders()->set('Content-Length', '100');
        $request->getHeaders()->set('Authorization', 'Bearer private-token');
        $request->setQueryParams(['token' => 'private-query']);
        $request->setBodyParams([
            'title' => 'private-title',
            'password' => 'private-password',
            'completed' => true,
        ]);

        $target = new SentryLogTarget([
            'dsn' => 'http://public@example.com/1',
            'environment' => 'test',
            'transport' => $transport,
            'requestContext' => $context,
            'request' => $request,
        ]);

        $originalMethod = $_SERVER['REQUEST_METHOD'] ?? null;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        try {
            $target->messages = [
                ['normal info', Logger::LEVEL_INFO, 'app\\test', time()],
                [['event' => 'task_event_requeued', 'destination' => 'retry'], Logger::LEVEL_WARNING, 'app\\test', time()],
                [new RuntimeException('private-exception'), Logger::LEVEL_ERROR, 'app\\test', time()],
                [['event' => 'task_event_requeued', 'destination' => 'dead'], Logger::LEVEL_WARNING, 'app\\test', time()],
            ];
            $target->export();
        } finally {
            if ($originalMethod === null) {
                unset($_SERVER['REQUEST_METHOD']);
            } else {
                $_SERVER['REQUEST_METHOD'] = $originalMethod;
            }
        }

        self::assertCount(1, $transport->events);
        $exceptionEvent = $transport->events[0];
        self::assertSame('request-123', $exceptionEvent->getTags()['request_id']);
        self::assertSame(true, $exceptionEvent->getRequest()['data']['completed']);
        self::assertArrayNotHasKey('headers', $exceptionEvent->getRequest());
        self::assertArrayNotHasKey('query_string', $exceptionEvent->getRequest());
        self::assertSame('Сообщение скрыто для защиты данных.', $exceptionEvent->getExceptions()[0]->getValue());
        self::assertSame([], $exceptionEvent->getExtra());
        foreach ($exceptionEvent->getExceptions()[0]->getStacktrace()?->getFrames() ?? [] as $frame) {
            self::assertSame([], $frame->getVars());
        }
        $payload = (new PayloadSerializer(new Options(['dsn' => 'http://public@example.com/1'])))
            ->serialize($exceptionEvent);
        self::assertStringNotContainsString('private-title', $payload);
        self::assertStringNotContainsString('private-password', $payload);
        self::assertStringNotContainsString('private-exception', $payload);
        self::assertStringNotContainsString('private-token', $payload);
        self::assertStringNotContainsString('private-query', $payload);
    }

    public function testRepeatedSeriousErrorsAreNotSilentlyDropped(): void
    {
        $transport = new RecordingSentryTransport();
        $target = new SentryLogTarget([
            'dsn' => 'http://public@example.com/1',
            'transport' => $transport,
        ]);
        $exception = new RuntimeException('Temporary failure');
        $target->messages = [
            [$exception, Logger::LEVEL_ERROR, 'app\\worker', time()],
            [$exception, Logger::LEVEL_ERROR, 'app\\worker', time()],
        ];

        $target->export();

        self::assertCount(2, $transport->events);
    }

    public function testScalarErrorIsNotMislabelledAsDeadLetterEvent(): void
    {
        $transport = new RecordingSentryTransport();
        $target = new SentryLogTarget([
            'dsn' => 'http://public@example.com/1',
            'transport' => $transport,
        ]);
        $target->messages = [
            ['sensitive-value', Logger::LEVEL_ERROR, 'app\\test', time()],
        ];

        $target->export();

        self::assertCount(1, $transport->events);
        self::assertSame('Ошибка приложения.', $transport->events[0]->getMessage());
    }

    public function testExpectedHttpErrorsAreExcluded(): void
    {
        $transport = new RecordingSentryTransport();
        $target = new SentryLogTarget([
            'dsn' => 'http://public@example.com/1',
            'transport' => $transport,
            'except' => ['yii\\web\\HttpException:4*'],
            'logVars' => [],
            'exportInterval' => 1,
        ]);

        $target->collect([
            [new NotFoundHttpException('Missing'), Logger::LEVEL_ERROR, 'yii\\web\\HttpException:404', time()],
            [new RuntimeException('Failure'), Logger::LEVEL_ERROR, 'app\\test', time()],
        ], true);

        self::assertCount(1, $transport->events);
    }
}

final class RecordingSentryTransport implements TransportInterface
{
    /** @var list<Event> */
    public array $events = [];

    public function send(Event $event): Result
    {
        $this->events[] = $event;

        return new Result(ResultStatus::success(), $event);
    }

    public function close(?int $timeout = null): Result
    {
        return new Result(ResultStatus::success());
    }
}
