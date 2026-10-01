<?php

declare(strict_types=1);

namespace Catalog\Catalog;

use Catalog\Source\SourceAdapter;
use Catalog\Support\Clock;
use Psr\Log\LoggerInterface;

/**
 * Read-through lookup. The cache is consulted first; the source is called only
 * when the local copy is missing or stale, and the result is written back.
 *
 * A stale copy is preferred over an error: if the source fails while refreshing
 * something already cached, the stale product is returned and the failure logged.
 * A caller asking for a price would rather have a 15-minute-old number than a 503.
 */
final class CatalogService
{
    public function __construct(
        private readonly ProductStore $products,
        private readonly SourceAdapter $source,
        private readonly Clock $clock,
        private readonly LoggerInterface $logger,
        private readonly int $ttlSeconds,
    ) {
    }

    public function findById(string $sourceId): ?Product
    {
        return $this->readThrough(
            $this->products->findBySourceId($sourceId),
            fn (): ?Product => $this->source->findById($sourceId),
            ['lookup' => 'id', 'source_id' => $sourceId],
        );
    }

    public function findBySku(string $sku): ?Product
    {
        return $this->readThrough(
            $this->products->findBySku($sku),
            fn (): ?Product => $this->source->findBySku($sku),
            ['lookup' => 'sku', 'sku' => $sku],
        );
    }

    /**
     * Search goes to the source and the results are cached individually, so a
     * later lookup of any one of them is served locally.
     *
     * @return list<Product>
     */
    public function search(string $keyword, int $limit, int $offset): array
    {
        try {
            $found = $this->source->search($keyword, $limit, $offset);
        } catch (\Catalog\Source\SourceException $e) {
            // Falling back to whatever the cache already holds for this keyword.
            $this->logger->warning('Source search failed, serving cached matches', [
                'retryable' => $e->isRetryable(),
                'reason' => $e->getMessage(),
            ]);

            return $this->products->searchByTitle($keyword, $limit, $offset);
        }

        $now = $this->clock->now();
        $stored = [];
        foreach ($found as $product) {
            $fresh = $product->withFetchedAt($now);
            $this->products->save($fresh);
            $stored[] = $fresh;
        }

        return $stored;
    }

    /**
     * @param callable(): ?Product $fetch
     * @param array<string, mixed> $logContext
     */
    private function readThrough(?Product $cached, callable $fetch, array $logContext): ?Product
    {
        $now = $this->clock->now();

        if ($cached !== null && !$cached->isStale($now, $this->ttlSeconds)) {
            return $cached;
        }

        try {
            $fetched = $fetch();
        } catch (\Catalog\Source\SourceException $e) {
            $this->logger->warning('Source lookup failed', $logContext + [
                'retryable' => $e->isRetryable(),
                'reason' => $e->getMessage(),
                'served_stale' => $cached !== null,
            ]);

            return $cached;
        }

        if ($fetched === null) {
            return $cached;
        }

        // A lookup by id carries no SKU. Writing it back as-is would erase a SKU
        // mapping an earlier lookup had established, so the cached one is kept.
        if ($fetched->sku === null && $cached?->sku !== null) {
            $fetched = new Product(
                $fetched->sourceId,
                $fetched->title,
                $cached->sku,
                $fetched->brand,
                $fetched->category,
                $fetched->price,
                $fetched->attributes,
            );
        }

        $fresh = $fetched->withFetchedAt($now);
        $this->products->save($fresh);

        return $fresh;
    }
}
