<?php

declare(strict_types=1);

namespace Catalog\Source;

use Catalog\Catalog\Product;
use Catalog\RateLimit\RateLimiter;

/**
 * Wraps a source so every call spends a token first.
 *
 * A decorator rather than a check inside each caller: the limit is a property of
 * the source, and this way CatalogService and the worker cannot forget to apply
 * it. Each operation has its own bucket, because a source quotas them separately
 * and a burst of searches should not stop a price submission.
 */
final class RateLimitedSource implements SourceAdapter
{
    public function __construct(
        private readonly SourceAdapter $inner,
        private readonly RateLimiter $limiter,
    ) {
    }

    public function search(string $keyword, int $limit, int $offset): array
    {
        $this->spend('search');

        return $this->inner->search($keyword, $limit, $offset);
    }

    public function findById(string $sourceId): ?Product
    {
        $this->spend('get_product');

        return $this->inner->findById($sourceId);
    }

    public function findBySku(string $sku): ?Product
    {
        $this->spend('get_product');

        return $this->inner->findBySku($sku);
    }

    public function submitBatch(string $type, array $rows): string
    {
        $this->spend('submit_batch');

        return $this->inner->submitBatch($type, $rows);
    }

    public function fetchJobResult(string $sourceJobId): ?BatchResult
    {
        $this->spend('fetch_result');

        return $this->inner->fetchJobResult($sourceJobId);
    }

    private function spend(string $operation): void
    {
        $waitSeconds = $this->limiter->reserve($operation);
        if ($waitSeconds === null) {
            return;
        }

        // Refused locally, before the request leaves the process. The source never
        // sees it, so the real quota is not spent on a call that would be rejected.
        throw new ThrottledException(
            "Local rate limit reached for {$operation}.",
            (int) ceil($waitSeconds),
        );
    }
}
