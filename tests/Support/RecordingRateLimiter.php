<?php

declare(strict_types=1);

namespace Catalog\Tests\Support;

use Catalog\RateLimit\RateLimiter;

/**
 * Allows the first N reservations, then makes every caller wait. Records which
 * operation each call named, so a test can assert on bucket separation.
 */
final class RecordingRateLimiter implements RateLimiter
{
    public int $calls = 0;

    /** @var list<string> */
    public array $operations = [];

    public function __construct(
        private readonly int $allowed,
        private readonly float $wait = 2.5,
    ) {
    }

    public function reserve(string $operation): ?float
    {
        $this->operations[] = $operation;

        return ++$this->calls <= $this->allowed ? null : $this->wait;
    }
}
