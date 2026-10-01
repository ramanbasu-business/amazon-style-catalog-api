<?php

declare(strict_types=1);

namespace Catalog\Source;

use Catalog\Catalog\Product;

/**
 * The seam where a real marketplace would be plugged in. Nothing above this
 * interface knows which marketplace is in use, or that the only implementation
 * shipped in this repo is a mock.
 *
 * Implementations throw ThrottledException when the source refuses the request
 * for rate reasons, and SourceException for anything else that failed.
 */
interface SourceAdapter
{
    /** @return list<Product> */
    public function search(string $keyword, int $limit, int $offset): array;

    public function findById(string $sourceId): ?Product;

    public function findBySku(string $sku): ?Product;

    /**
     * Submits a batch of changes. Returns the source's own job identifier; the
     * outcome is not known yet and must be polled with fetchJobResult().
     *
     * @param list<array{line_number: int, sku: string, payload: array<string, mixed>}> $rows
     */
    public function submitBatch(string $type, array $rows): string;

    /**
     * Null while the source is still processing. Otherwise a per-row outcome.
     */
    public function fetchJobResult(string $sourceJobId): ?BatchResult;
}
