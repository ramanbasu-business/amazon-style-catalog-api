<?php

declare(strict_types=1);

namespace Catalog\RateLimit;

use Catalog\Support\Clock;
use Doctrine\DBAL\Connection;

/**
 * A token bucket per source operation, held in the database.
 *
 * The bucket is in the database rather than in process memory because the API
 * and the worker are separate containers sharing one quota at the source. Two
 * processes each holding their own in-memory bucket would together spend twice
 * the allowance and get throttled.
 *
 * The row is read with FOR UPDATE, so two workers cannot both see the last token
 * and both spend it.
 */
final class TokenBucket implements RateLimiter
{
    public function __construct(
        private readonly Connection $connection,
        private readonly Clock $clock,
        private readonly float $burst,
        private readonly float $refillPerSecond,
    ) {
    }

    /** @phpstan-impure */
    public function reserve(string $operation): ?float
    {
        $now = $this->clock->now();
        $nowMicro = (float) $now->format('U.u');

        return $this->connection->transactional(function (Connection $tx) use ($operation, $nowMicro): ?float {
            $row = $tx->fetchAssociative(
                'SELECT tokens, UNIX_TIMESTAMP(last_refilled) AS last_refilled '
                . 'FROM rate_limit_buckets WHERE operation = ? FOR UPDATE',
                [$operation]
            );

            if ($row === false) {
                // First use of this operation: a full bucket, minus the token we spend.
                $this->upsert($tx, $operation, $this->burst - 1.0, $nowMicro);

                return null;
            }

            $elapsed = max(0.0, $nowMicro - (float) $row['last_refilled']);
            $tokens = min($this->burst, (float) $row['tokens'] + ($elapsed * $this->refillPerSecond));

            if ($tokens < 1.0) {
                // Not enough for a whole token yet. Report how long until there is one,
                // without touching the stored tokens.
                $deficit = 1.0 - $tokens;
                $this->upsert($tx, $operation, $tokens, $nowMicro);

                return $this->refillPerSecond > 0.0 ? $deficit / $this->refillPerSecond : null;
            }

            $this->upsert($tx, $operation, $tokens - 1.0, $nowMicro);

            return null;
        });
    }

    private function upsert(Connection $tx, string $operation, float $tokens, float $nowMicro): void
    {
        $tx->executeStatement(
            'INSERT INTO rate_limit_buckets (operation, tokens, last_refilled) '
            . 'VALUES (?, ?, FROM_UNIXTIME(?)) '
            . 'ON DUPLICATE KEY UPDATE tokens = VALUES(tokens), last_refilled = VALUES(last_refilled)',
            [$operation, $tokens, $nowMicro]
        );
    }
}
