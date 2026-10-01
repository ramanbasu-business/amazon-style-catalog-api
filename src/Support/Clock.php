<?php

declare(strict_types=1);

namespace Catalog\Support;

use DateTimeImmutable;

/**
 * Time is injected rather than read from the global clock, so cache staleness,
 * token-bucket refill and backoff schedules can be tested without sleeping.
 */
interface Clock
{
    public function now(): DateTimeImmutable;
}
