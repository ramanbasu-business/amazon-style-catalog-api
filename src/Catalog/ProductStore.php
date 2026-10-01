<?php

declare(strict_types=1);

namespace Catalog\Catalog;

/**
 * The cache behind the catalog. An interface rather than a concrete class so the
 * service layer can be tested without a database, and so the storage choice is
 * one named seam rather than a type scattered through the code.
 */
interface ProductStore
{
    public function findBySourceId(string $sourceId): ?Product;

    public function findBySku(string $sku): ?Product;

    /** @return list<Product> */
    public function searchByTitle(string $keyword, int $limit, int $offset): array;

    public function save(Product $product): void;
}
