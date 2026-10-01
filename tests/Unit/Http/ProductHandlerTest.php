<?php

declare(strict_types=1);

namespace Catalog\Tests\Unit\Http;

use Catalog\Catalog\CatalogService;
use Catalog\Catalog\Money;
use Catalog\Catalog\Product;
use Catalog\Catalog\ProductStore;
use Catalog\Source\MockMarketplace;
use Catalog\Source\SourceAdapter;
use Catalog\Support\Clock;
use Catalog\Tests\Support\AppTestCase;
use Catalog\Tests\Support\InMemoryProductRepository;
use Monolog\Handler\NullHandler;
use Monolog\Logger;

final class ProductHandlerTest extends AppTestCase
{
    private InMemoryProductRepository $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = new InMemoryProductRepository([
            (new Product(
                'MP0000AAAA',
                'Northwind Kettle 1200',
                'SKU-123',
                'Northwind',
                'Kitchen',
                new Money(1999, 'GBP'),
            ))->withFetchedAt($this->clock->now()),
        ]);

        $this->override(ProductStore::class, fn (): ProductStore => $this->store);
        $this->override(SourceAdapter::class, fn (): SourceAdapter => new MockMarketplace($this->clock));
        $this->override(CatalogService::class, fn (Clock $clock): CatalogService => new CatalogService(
            $this->store,
            new MockMarketplace($clock),
            $clock,
            new Logger('test', [new NullHandler()]),
            900,
        ));
    }

    public function testGetById_WhenCached_ReturnsTheProduct(): void
    {
        $response = $this->request('GET', '/v1/products/MP0000AAAA');

        self::assertSame(200, $response->getStatusCode());
        $body = $this->decode($response);
        self::assertSame('MP0000AAAA', $body['id']);
        self::assertSame('SKU-123', $body['sku']);
        self::assertSame(['amount' => '19.99', 'currency' => 'GBP'], $body['price']);
    }

    public function testGetBySku_WhenMapped_ReturnsTheProduct(): void
    {
        $response = $this->request('GET', '/v1/products/sku/SKU-123');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('MP0000AAAA', $this->decode($response)['id']);
    }

    public function testGetById_WhenUnknown_Returns404Problem(): void
    {
        $response = $this->request('GET', '/v1/products/NOPE999');

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->getHeaderLine('Content-Type'));
    }

    public function testGetById_WithMalformedId_Returns422BeforeLookup(): void
    {
        $response = $this->request('GET', '/v1/products/not%20a%20valid%20id!');

        self::assertSame(422, $response->getStatusCode());
        self::assertArrayHasKey('id', $this->decode($response)['errors']);
    }

    public function testSearch_WithoutKeyword_Returns422(): void
    {
        $response = $this->request('GET', '/v1/products');

        self::assertSame(422, $response->getStatusCode());
        self::assertArrayHasKey('q', $this->decode($response)['errors']);
    }

    public function testSearch_WithOversizedLimit_Returns422(): void
    {
        $response = $this->request('GET', '/v1/products?q=kettle&limit=5000');

        self::assertSame(422, $response->getStatusCode());
        self::assertArrayHasKey('limit', $this->decode($response)['errors']);
    }

    public function testSearch_WithNonNumericOffset_Returns422(): void
    {
        $response = $this->request('GET', '/v1/products?q=kettle&offset=abc');

        self::assertSame(422, $response->getStatusCode());
        self::assertArrayHasKey('offset', $this->decode($response)['errors']);
    }

    public function testSearch_WithKeyword_ReturnsResultsAndCachesThem(): void
    {
        $response = $this->request('GET', '/v1/products?q=kettle&limit=5');

        self::assertSame(200, $response->getStatusCode());
        $body = $this->decode($response);

        self::assertLessThanOrEqual(5, $body['meta']['count']);
        self::assertGreaterThan(0, $body['meta']['count']);
        self::assertSame(5, $body['meta']['limit']);
        self::assertSame($body['meta']['count'], count($body['data']));
        self::assertGreaterThan(0, $this->store->saveCount);
    }
}
