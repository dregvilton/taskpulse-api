<?php

declare(strict_types=1);

namespace tests\unit;

use app\components\RequestContext;
use PHPUnit\Framework\TestCase;

final class RequestContextTest extends TestCase
{
    public function testAcceptsSafeRequestId(): void
    {
        $context = new RequestContext();
        $context->start('request_123-ABC');

        self::assertSame('request_123-ABC', $context->getRequestId());
    }

    public function testReplacesUnsafeRequestId(): void
    {
        $context = new RequestContext();
        $context->start("unsafe\nvalue");

        self::assertMatchesRegularExpression('/\A[a-f0-9]{32}\z/D', (string) $context->getRequestId());
    }
}
