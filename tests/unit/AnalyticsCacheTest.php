<?php

declare(strict_types=1);

namespace tests\unit;

use app\modules\analytics\forms\AnalyticsFilterForm;
use app\services\AnalyticsCache;
use JsonException;
use PHPUnit\Framework\TestCase;
use yii\redis\Connection;

final class AnalyticsCacheTest extends TestCase
{
    /**
     * @throws JsonException
     */
    public function testKeyDependsOnFiltersAndGeneration(): void
    {
        $cache = new AnalyticsCache(new Connection());
        $first = new AnalyticsFilterForm();
        $first->authorId = '7';
        $first->createdFrom = '2026-09-01T00:00:00+00:00';

        $same = new AnalyticsFilterForm();
        $same->authorId = 7;
        $same->createdFrom = '2026-09-01T00:00:00+00:00';

        $other = new AnalyticsFilterForm();
        $other->authorId = 8;
        $other->createdFrom = '2026-09-01T00:00:00+00:00';

        self::assertSame($cache->getKey($first, 2), $cache->getKey($same, 2));
        self::assertNotSame($cache->getKey($first, 2), $cache->getKey($other, 2));
        self::assertNotSame($cache->getKey($first, 2), $cache->getKey($first, 3));
    }
}
