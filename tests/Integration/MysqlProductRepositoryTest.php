<?php

declare(strict_types=1);

namespace Catalog\Tests\Integration;

use Catalog\Catalog\Money;
use Catalog\Catalog\MysqlProductRepository;
use Catalog\Catalog\Product;
use DateTimeImmutable;
use DateTimeZone;

final class MysqlProductRepositoryTest extends DatabaseTestCase
{
    private MysqlProductRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new MysqlProductRepository($this->connection);
    }

    private function product(string $id, string $title, ?string $sku = null): Product
    {
        return new Product(
            sourceId: $id,
            title: $title,
            sku: $sku,
            brand: 'Northwind',
            category: 'Kitchen',
            price: new Money(1999, 'GBP'),
            attributes: ['colour' => 'Sage', 'weight_grams' => 850],
            fetchedAt: new DateTimeImmutable('2026-10-01 12:00:00', new DateTimeZone('UTC')),
        );
    }

    public function testSavesAndReadsBackEveryField(): void
    {
        $this->repository->save($this->product('MP0000AAAA', 'Northwind Kettle 1200', 'SKU-123'));

        $found = $this->repository->findBySourceId('MP0000AAAA');

        self::assertNotNull($found);
        self::assertSame('Northwind Kettle 1200', $found->title);
        self::assertSame('SKU-123', $found->sku);
        self::assertSame('Northwind', $found->brand);
        self::assertSame('Kitchen', $found->category);
        self::assertSame('19.99', $found->price?->toDecimalString());
        self::assertSame('GBP', $found->price->currency);
        self::assertSame('Sage', $found->attributes['colour']);
        self::assertSame(850, $found->attributes['weight_grams']);
        self::assertSame('2026-10-01T12:00:00+00:00', $found->fetchedAt?->format(DATE_ATOM));
    }

    public function testSavingTheSameIdTwiceUpdatesRatherThanDuplicates(): void
    {
        $this->repository->save($this->product('MP0000AAAA', 'First title', 'SKU-123'));
        $this->repository->save($this->product('MP0000AAAA', 'Second title', 'SKU-123'));

        self::assertSame('Second title', $this->repository->findBySourceId('MP0000AAAA')?->title);
        self::assertSame(
            1,
            (int) $this->connection->fetchOne('SELECT COUNT(*) FROM products WHERE source_id = ?', ['MP0000AAAA'])
        );
    }

    public function testFindsBySku(): void
    {
        $this->repository->save($this->product('MP0000AAAA', 'Northwind Kettle 1200', 'SKU-123'));

        self::assertSame('MP0000AAAA', $this->repository->findBySku('SKU-123')?->sourceId);
        self::assertNull($this->repository->findBySku('SKU-999'));
    }

    public function testStoresAProductWithNoPriceOrSku(): void
    {
        $bare = new Product('MP0000BBBB', 'Unpriced item');
        $this->repository->save($bare);

        $found = $this->repository->findBySourceId('MP0000BBBB');

        self::assertNotNull($found);
        self::assertNull($found->price);
        self::assertNull($found->sku);
        self::assertSame([], $found->attributes);
    }

    public function testSearchMatchesPartialTitlesAndPaginates(): void
    {
        $this->repository->save($this->product('MP0000AAAA', 'Northwind Kettle 1200'));
        $this->repository->save($this->product('MP0000BBBB', 'Northwind Kettle 1800'));
        $this->repository->save($this->product('MP0000CCCC', 'Cedarline Planter 300'));

        $all = $this->repository->searchByTitle('Kettle', 10, 0);
        self::assertCount(2, $all);

        $firstPage = $this->repository->searchByTitle('Kettle', 1, 0);
        $secondPage = $this->repository->searchByTitle('Kettle', 1, 1);

        self::assertCount(1, $firstPage);
        self::assertCount(1, $secondPage);
        self::assertNotSame($firstPage[0]->sourceId, $secondPage[0]->sourceId);
    }

    public function testWildcardInTheKeywordIsTreatedAsLiteralText(): void
    {
        // Without escaping, a bare "%" would match every row in the table.
        $this->repository->save($this->product('MP0000AAAA', 'Northwind Kettle 1200'));
        $this->repository->save($this->product('MP0000BBBB', 'Cedarline Planter 300'));

        self::assertCount(0, $this->repository->searchByTitle('%', 10, 0));
    }

    public function testUnicodeTitlesSurviveTheRoundTrip(): void
    {
        $this->repository->save($this->product('MP0000DDDD', 'Café Kettle — 1.2L'));

        self::assertSame('Café Kettle — 1.2L', $this->repository->findBySourceId('MP0000DDDD')?->title);
    }
}
