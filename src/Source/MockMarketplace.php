<?php

declare(strict_types=1);

namespace Catalog\Source;

use Catalog\Catalog\Money;
use Catalog\Catalog\Product;
use Catalog\Support\Clock;

/**
 * A stand-in marketplace, so the whole service runs and is testable with no
 * marketplace account and no network.
 *
 * It behaves badly on purpose: it throttles a share of requests, completes
 * batches only after a delay, and rejects a share of rows. Those are the paths
 * that matter in this project, and without them they would be unreachable.
 *
 * Every product here is generated. No real brand, seller or identifier appears.
 */
final class MockMarketplace implements SourceAdapter
{
    private const BRANDS = ['Northwind', 'Lumenica', 'Harbright', 'Cedarline', 'Balmoor', 'Tessaro'];
    private const CATEGORIES = ['Kitchen', 'Outdoor', 'Office', 'Lighting', 'Storage'];
    private const NOUNS = ['Kettle', 'Lantern', 'Desk Lamp', 'Storage Bin', 'Chopping Board', 'Planter'];

    /** @var array<string, array{type: string, rows: list<array{line_number: int, sku: string}>, submitted_at: int}> */
    private array $batches = [];

    public function __construct(
        private readonly Clock $clock,
        private readonly float $throttleRate = 0.0,
        private readonly float $rowRejectRate = 0.0,
        private readonly int $jobCompleteAfterSeconds = 5,
        /** Seeded so a test run is repeatable; null uses a random seed. */
        private readonly ?int $seed = null,
    ) {
    }

    public function search(string $keyword, int $limit, int $offset): array
    {
        $this->maybeThrottle();

        $keyword = trim($keyword);
        if ($keyword === '') {
            return [];
        }

        // A deterministic result set per keyword: the same search returns the same
        // products, which is what makes the cache behaviour testable.
        $total = 3 + ($this->hash($keyword) % 18);
        $products = [];
        for ($i = 0; $i < $total; $i++) {
            $products[] = $this->generate($keyword, $i);
        }

        return array_values(array_slice($products, $offset, $limit));
    }

    public function findById(string $sourceId): ?Product
    {
        $this->maybeThrottle();

        if (!preg_match('/^MP[0-9A-F]{8}$/', $sourceId)) {
            return null;
        }

        return $this->generate($sourceId, 0, $sourceId);
    }

    public function findBySku(string $sku): ?Product
    {
        $this->maybeThrottle();

        if (!preg_match('/^SKU-[0-9A-Z-]{1,32}$/', $sku)) {
            return null;
        }

        $product = $this->generate($sku, 0);

        return new Product(
            $product->sourceId,
            $product->title,
            $sku,
            $product->brand,
            $product->category,
            $product->price,
            $product->attributes,
        );
    }

    public function submitBatch(string $type, array $rows): string
    {
        $this->maybeThrottle();

        $id = 'MOCKJOB-' . strtoupper(substr(hash('sha256', $type . json_encode($rows) . uniqid('', true)), 0, 12));
        $this->batches[$id] = [
            'type' => $type,
            'rows' => array_map(
                fn (array $row): array => ['line_number' => $row['line_number'], 'sku' => $row['sku']],
                $rows
            ),
            'submitted_at' => $this->clock->now()->getTimestamp(),
        ];

        return $id;
    }

    public function fetchJobResult(string $sourceJobId): ?BatchResult
    {
        $batch = $this->batches[$sourceJobId] ?? null;
        if ($batch === null) {
            throw SourceException::permanent("Unknown source job: {$sourceJobId}");
        }

        $elapsed = $this->clock->now()->getTimestamp() - $batch['submitted_at'];
        if ($elapsed < $this->jobCompleteAfterSeconds) {
            return null;
        }

        $outcomes = [];
        foreach ($batch['rows'] as $row) {
            // Rejection is a deterministic function of the SKU, so the same row is
            // rejected on every run and a test can assert on a specific line.
            $reject = $this->rowRejectRate > 0.0
                && ($this->hash($row['sku']) % 100) < (int) round($this->rowRejectRate * 100);

            $outcomes[] = new RowOutcome(
                $row['line_number'],
                !$reject,
                $reject ? 'Value rejected by the marketplace for this SKU.' : null,
            );
        }

        return new BatchResult($outcomes);
    }

    private function maybeThrottle(): void
    {
        if ($this->throttleRate <= 0.0) {
            return;
        }

        $roll = $this->seed === null
            ? mt_rand(0, 99)
            : ($this->hash((string) $this->seed . $this->clock->now()->format('U.u')) % 100);

        if ($roll < (int) round($this->throttleRate * 100)) {
            throw new ThrottledException('The source refused the request: request quota exceeded.', 2);
        }
    }

    private function generate(string $token, int $index, ?string $forceId = null): Product
    {
        $h = $this->hash($token . ':' . $index);

        $brand = self::BRANDS[$h % count(self::BRANDS)];
        $noun = self::NOUNS[intdiv($h, 7) % count(self::NOUNS)];
        $category = self::CATEGORIES[intdiv($h, 13) % count(self::CATEGORIES)];
        $id = $forceId ?? 'MP' . strtoupper(substr(hash('sha256', $token . $index), 0, 8));

        return new Product(
            sourceId: $id,
            title: sprintf('%s %s %s', $brand, $noun, 1000 + ($h % 9000)),
            sku: null,
            brand: $brand,
            category: $category,
            price: new Money(500 + ($h % 24500), 'GBP'),
            attributes: [
                'colour' => ['Black', 'White', 'Sage', 'Oak'][$h % 4],
                'weight_grams' => 200 + ($h % 3800),
            ],
        );
    }

    private function hash(string $value): int
    {
        return (int) hexdec(substr(hash('sha256', $value), 0, 8));
    }
}
