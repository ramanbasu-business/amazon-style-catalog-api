<?php

declare(strict_types=1);

namespace Catalog\Catalog;

use DateTimeImmutable;

/**
 * The project's own product shape. Source responses are normalised into this
 * before anything else sees them, so a change at the source does not reach the
 * API contract.
 */
final class Product
{
    /** @param array<string, scalar> $attributes */
    public function __construct(
        public readonly string $sourceId,
        public readonly string $title,
        public readonly ?string $sku = null,
        public readonly ?string $brand = null,
        public readonly ?string $category = null,
        public readonly ?Money $price = null,
        public readonly array $attributes = [],
        public readonly ?DateTimeImmutable $fetchedAt = null,
    ) {
    }

    public function withFetchedAt(DateTimeImmutable $fetchedAt): self
    {
        return new self(
            $this->sourceId,
            $this->title,
            $this->sku,
            $this->brand,
            $this->category,
            $this->price,
            $this->attributes,
            $fetchedAt,
        );
    }

    /** True when the cached copy is older than the allowed age. */
    public function isStale(DateTimeImmutable $now, int $ttlSeconds): bool
    {
        if ($this->fetchedAt === null) {
            return true;
        }

        return ($now->getTimestamp() - $this->fetchedAt->getTimestamp()) >= $ttlSeconds;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->sourceId,
            'sku' => $this->sku,
            'title' => $this->title,
            'brand' => $this->brand,
            'category' => $this->category,
            'price' => $this->price === null ? null : [
                'amount' => $this->price->toDecimalString(),
                'currency' => $this->price->currency,
            ],
            'attributes' => $this->attributes,
            'fetched_at' => $this->fetchedAt?->format(DATE_ATOM),
        ];
    }
}
