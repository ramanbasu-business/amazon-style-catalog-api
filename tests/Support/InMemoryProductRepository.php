<?php

declare(strict_types=1);

namespace Catalog\Tests\Support;

use Catalog\Catalog\Product;
use Catalog\Catalog\ProductStore;

/**
 * Stands in for the database in unit tests. The real repository is covered
 * separately by the integration suite against MySQL.
 */
final class InMemoryProductRepository implements ProductStore
{
    /** @var array<string, Product> */
    private array $bySourceId = [];

    public int $saveCount = 0;

    /** @param list<Product> $products */
    public function __construct(array $products = [])
    {
        foreach ($products as $product) {
            $this->bySourceId[$product->sourceId] = $product;
        }
    }

    public function findBySourceId(string $sourceId): ?Product
    {
        return $this->bySourceId[$sourceId] ?? null;
    }

    public function findBySku(string $sku): ?Product
    {
        foreach ($this->bySourceId as $product) {
            if ($product->sku === $sku) {
                return $product;
            }
        }

        return null;
    }

    public function searchByTitle(string $keyword, int $limit, int $offset): array
    {
        $matches = array_values(array_filter(
            $this->bySourceId,
            fn (Product $p): bool => stripos($p->title, $keyword) !== false
        ));

        return array_values(array_slice($matches, $offset, $limit));
    }

    public function save(Product $product): void
    {
        $this->saveCount++;
        $this->bySourceId[$product->sourceId] = $product;
    }
}
