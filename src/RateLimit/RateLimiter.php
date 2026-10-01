<?php

declare(strict_types=1);

namespace Catalog\RateLimit;

interface RateLimiter
{
    /**
     * Spends one token for the operation if one is available.
     *
     * Returns the wait in seconds when the caller must hold off, or null when the
     * request may proceed. Callers decide whether to wait or shed the request, so
     * a worker can sleep where a web request would rather fail fast.
     *
     * Spending a token changes stored state, so two identical calls can return
     * different answers.
     *
     * @phpstan-impure
     */
    public function reserve(string $operation): ?float;
}
