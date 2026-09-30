<?php

declare(strict_types=1);

namespace tests\api;

use JsonException;

final class OperationsApiTest extends ApiTestCase
{
    /**
     * @throws JsonException
     */
    public function testHealthAndErrorResponsesHaveRequestId(): void
    {
        $health = $this->request('GET', '/health');
        self::assertSame(200, $health['status']);
        self::assertSame('ok', $health['body']['services']['postgres']);
        self::assertSame('ok', $health['body']['services']['redis']);
        self::assertSame('ok', $health['body']['services']['rabbitmq']);
        self::assertMatchesRegularExpression('/\A[a-f0-9]{32}\z/D', $health['headers']['x-request-id']);

        $missing = $this->request('GET', '/missing');
        self::assertSame(404, $missing['status']);
        self::assertMatchesRegularExpression('/\A[a-f0-9]{32}\z/D', $missing['headers']['x-request-id']);
    }
}
