<?php

declare(strict_types=1);

namespace Catalog\Support;

use DateTimeImmutable;

/**
 * Test clock. Lives in src rather than tests so the mock marketplace and the
 * worker can also be driven deterministically from a console command.
 */
final class FrozenClock implements Clock
{
    private DateTimeImmutable $now;

    public function __construct(DateTimeImmutable $now)
    {
        $this->now = $now;
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function advance(int $seconds): void
    {
        $this->now = $this->now->modify("+{$seconds} seconds");
    }

    public function set(DateTimeImmutable $now): void
    {
        $this->now = $now;
    }
}
