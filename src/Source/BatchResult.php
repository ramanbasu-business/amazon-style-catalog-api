<?php

declare(strict_types=1);

namespace Catalog\Source;

/**
 * The outcome of a submitted batch. A batch can be partially accepted, so the
 * result is per row rather than a single success flag.
 */
final class BatchResult
{
    /** @param list<RowOutcome> $rows */
    public function __construct(public readonly array $rows)
    {
    }

    public function acceptedCount(): int
    {
        return count(array_filter($this->rows, fn (RowOutcome $r): bool => $r->accepted));
    }

    public function rejectedCount(): int
    {
        return count($this->rows) - $this->acceptedCount();
    }
}
