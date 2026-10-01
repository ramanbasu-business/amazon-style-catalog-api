<?php

declare(strict_types=1);

namespace Catalog\Tests\Unit\Catalog;

use Catalog\Catalog\CatalogService;
use Catalog\Catalog\Money;
use Catalog\Catalog\Product;
use Catalog\Source\BatchResult;
use Catalog\Source\SourceAdapter;
use Catalog\Source\SourceException;
use Catalog\Source\ThrottledException;
use Catalog\Support\FrozenClock;
use Catalog\Tests\Support\InMemoryProductRepository;
use DateTimeImmutable;
use DateTimeZone;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

final class CatalogServiceTest extends TestCase
{
    private const TTL = 900;

    private FrozenClock $clock;

    protected function setUp(): void
    {
        $this->clock = new FrozenClock(new DateTimeImmutable('2026-10-01 12:00:00', new DateTimeZone('UTC')));
    }

    private function service(InMemoryProductRepository $store, SourceAdapter $source): CatalogService
    {
        return new CatalogService(
            $store,
            $source,
            $this->clock,
            new Logger('test', [new NullHandler()]),
            self::TTL,
        );
    }

    private function product(string $id, string $title = 'Northwind Kettle 1200', ?string $sku = null): Product
    {
        return new Product($id, $title, $sku, 'Northwind', 'Kitchen', new Money(1999, 'GBP'));
    }

    public function testFindById_WhenCacheIsFresh_DoesNotCallTheSource(): void
    {
        $cached = $this->product('MP0000AAAA')->withFetchedAt($this->clock->now());
        $store = new InMemoryProductRepository([$cached]);

        $source = $this->createMock(SourceAdapter::class);
        $source->expects(self::never())->method('findById');

        $result = $this->service($store, $source)->findById('MP0000AAAA');

        self::assertSame('MP0000AAAA', $result?->sourceId);
        self::assertSame(0, $store->saveCount);
    }

    public function testFindById_WhenNotCached_FetchesFromSourceAndCaches(): void
    {
        $store = new InMemoryProductRepository();

        $source = $this->createMock(SourceAdapter::class);
        $source->expects(self::once())
            ->method('findById')
            ->with('MP0000AAAA')
            ->willReturn($this->product('MP0000AAAA'));

        $result = $this->service($store, $source)->findById('MP0000AAAA');

        self::assertSame('MP0000AAAA', $result?->sourceId);
        self::assertEquals($this->clock->now(), $result->fetchedAt);
        self::assertSame(1, $store->saveCount);
        self::assertNotNull($store->findBySourceId('MP0000AAAA'));
    }

    public function testFindById_WhenCacheReachesTtl_RefreshesFromSource(): void
    {
        $cached = $this->product('MP0000AAAA', 'Old title')->withFetchedAt($this->clock->now());
        $store = new InMemoryProductRepository([$cached]);

        $source = $this->createMock(SourceAdapter::class);
        $source->expects(self::once())
            ->method('findById')
            ->willReturn($this->product('MP0000AAAA', 'New title'));

        $this->clock->advance(self::TTL);
        $result = $this->service($store, $source)->findById('MP0000AAAA');

        self::assertSame('New title', $result?->title);
    }

    public function testFindById_OneSecondBeforeTtl_StillServesCache(): void
    {
        $cached = $this->product('MP0000AAAA')->withFetchedAt($this->clock->now());
        $store = new InMemoryProductRepository([$cached]);

        $source = $this->createMock(SourceAdapter::class);
        $source->expects(self::never())->method('findById');

        $this->clock->advance(self::TTL - 1);

        self::assertNotNull($this->service($store, $source)->findById('MP0000AAAA'));
    }

    public function testFindById_WhenSourceThrottled_ServesStaleCopy(): void
    {
        $cached = $this->product('MP0000AAAA', 'Cached title')->withFetchedAt($this->clock->now());
        $store = new InMemoryProductRepository([$cached]);

        $source = $this->createMock(SourceAdapter::class);
        $source->method('findById')->willThrowException(new ThrottledException('quota exceeded'));

        $this->clock->advance(self::TTL + 60);
        $result = $this->service($store, $source)->findById('MP0000AAAA');

        // Stale data beats an error for a read of something already known.
        self::assertSame('Cached title', $result?->title);
        self::assertSame(0, $store->saveCount);
    }

    public function testFindById_WhenSourceFailsAndNothingCached_ReturnsNull(): void
    {
        $store = new InMemoryProductRepository();

        $source = $this->createMock(SourceAdapter::class);
        $source->method('findById')->willThrowException(SourceException::transient('timeout'));

        self::assertNull($this->service($store, $source)->findById('MP0000AAAA'));
    }

    public function testFindById_WhenSourceOmitsSku_KeepsTheKnownSku(): void
    {
        // The source returns no SKU on an id lookup. Writing that back unchanged
        // would drop the mapping an earlier SKU lookup established.
        $cached = $this->product('MP0000AAAA', 'Title', 'SKU-123')->withFetchedAt($this->clock->now());
        $store = new InMemoryProductRepository([$cached]);

        $source = $this->createMock(SourceAdapter::class);
        $source->method('findById')->willReturn($this->product('MP0000AAAA', 'Fresh title', null));

        $this->clock->advance(self::TTL + 1);
        $result = $this->service($store, $source)->findById('MP0000AAAA');

        self::assertSame('Fresh title', $result?->title);
        self::assertSame('SKU-123', $result->sku);
        self::assertSame('SKU-123', $store->findBySku('SKU-123')?->sku);
    }

    public function testSearch_WithSourceResults_CachesEveryResult(): void
    {
        $store = new InMemoryProductRepository();

        $source = $this->createMock(SourceAdapter::class);
        $source->method('search')->willReturn([
            $this->product('MP0000AAAA', 'Kettle one'),
            $this->product('MP0000BBBB', 'Kettle two'),
        ]);

        $results = $this->service($store, $source)->search('kettle', 20, 0);

        self::assertCount(2, $results);
        self::assertSame(2, $store->saveCount);
        self::assertNotNull($store->findBySourceId('MP0000BBBB'));
    }

    public function testSearch_WhenSourceFails_FallsBackToCachedMatches(): void
    {
        $store = new InMemoryProductRepository([
            $this->product('MP0000AAAA', 'Northwind Kettle 1200')->withFetchedAt($this->clock->now()),
            $this->product('MP0000BBBB', 'Cedarline Planter 300')->withFetchedAt($this->clock->now()),
        ]);

        $source = $this->createMock(SourceAdapter::class);
        $source->method('search')->willThrowException(new ThrottledException('quota exceeded'));

        $results = $this->service($store, $source)->search('kettle', 20, 0);

        self::assertCount(1, $results);
        self::assertSame('Northwind Kettle 1200', $results[0]->title);
    }

    public function testFindById_WhenSourceReturnsNothing_LeavesCacheUntouched(): void
    {
        $store = new InMemoryProductRepository();

        $source = $this->createMock(SourceAdapter::class);
        $source->method('findById')->willReturn(null);

        self::assertNull($this->service($store, $source)->findById('MP0000AAAA'));
        self::assertSame(0, $store->saveCount);
    }

    public function testBatchResult_WithMixedOutcomes_CountsAcceptedAndRejected(): void
    {
        $result = new BatchResult([
            new \Catalog\Source\RowOutcome(1, true),
            new \Catalog\Source\RowOutcome(2, false, 'rejected'),
            new \Catalog\Source\RowOutcome(3, true),
        ]);

        self::assertSame(2, $result->acceptedCount());
        self::assertSame(1, $result->rejectedCount());
    }
}
