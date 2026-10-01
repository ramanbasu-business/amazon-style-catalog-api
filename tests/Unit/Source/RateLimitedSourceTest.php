<?php

declare(strict_types=1);

namespace Catalog\Tests\Unit\Source;

use Catalog\Catalog\Product;
use Catalog\Source\RateLimitedSource;
use Catalog\Source\SourceAdapter;
use Catalog\Source\ThrottledException;
use Catalog\Tests\Support\RecordingRateLimiter;
use PHPUnit\Framework\TestCase;

final class RateLimitedSourceTest extends TestCase
{
    public function testFindById_WhenTokenAvailable_CallsTheSource(): void
    {
        $inner = $this->createMock(SourceAdapter::class);
        $inner->expects(self::once())
            ->method('findById')
            ->with('MP0000AAAA')
            ->willReturn(new Product('MP0000AAAA', 'Northwind Kettle 1200'));

        $source = new RateLimitedSource($inner, new RecordingRateLimiter(allowed: 1));

        self::assertSame('MP0000AAAA', $source->findById('MP0000AAAA')?->sourceId);
    }

    public function testFindById_WhenNoTokenAvailable_RefusesWithoutCallingTheSource(): void
    {
        // The point of the local limiter: a refused call never reaches the source,
        // so it does not spend the real quota.
        $inner = $this->createMock(SourceAdapter::class);
        $inner->expects(self::never())->method('findById');

        $source = new RateLimitedSource($inner, new RecordingRateLimiter(allowed: 0, wait: 2.5));

        try {
            $source->findById('MP0000AAAA');
            self::fail('Expected a ThrottledException.');
        } catch (ThrottledException $e) {
            self::assertTrue($e->isRetryable());
            self::assertSame(3, $e->retryAfterSeconds, 'The wait is rounded up to whole seconds.');
        }
    }

    public function testReserve_ForEachOperation_UsesASeparateBucket(): void
    {
        $limiter = new RecordingRateLimiter(allowed: 99);
        $inner = $this->createStub(SourceAdapter::class);

        $source = new RateLimitedSource($inner, $limiter);
        $source->search('kettle', 10, 0);
        $source->findById('MP0000AAAA');
        $source->findBySku('SKU-123');
        $source->submitBatch('price', []);
        $source->fetchJobResult('MOCKJOB-1');

        // Lookups by id and by SKU share one bucket; the rest are separate, so a
        // burst of searches cannot block a submission.
        self::assertSame(
            ['search', 'get_product', 'get_product', 'submit_batch', 'fetch_result'],
            $limiter->operations
        );
    }
}
