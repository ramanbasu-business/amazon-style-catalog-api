<?php

declare(strict_types=1);

namespace Catalog\Tests\Integration;

use Catalog\RateLimit\TokenBucket;
use Catalog\Support\FrozenClock;
use DateTimeImmutable;
use DateTimeZone;

/**
 * The bucket's whole purpose is to be shared between processes, so it is tested
 * against the real database rather than a fake.
 */
final class TokenBucketTest extends DatabaseTestCase
{
    private FrozenClock $clock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection->executeStatement('DELETE FROM rate_limit_buckets');
        $this->clock = new FrozenClock(new DateTimeImmutable('2026-10-01 12:00:00', new DateTimeZone('UTC')));
    }

    private function bucket(float $burst = 3.0, float $refillPerSecond = 1.0): TokenBucket
    {
        return new TokenBucket($this->connection, $this->clock, $burst, $refillPerSecond);
    }

    public function testReserve_WhenBurstIsSpent_RefusesFurtherCalls(): void
    {
        $bucket = $this->bucket(burst: 3.0);

        self::assertNull($bucket->reserve('search'));
        self::assertNull($bucket->reserve('search'));
        self::assertNull($bucket->reserve('search'));

        $wait = $bucket->reserve('search');
        self::assertNotNull($wait, 'The fourth call in the same instant must be refused.');
        self::assertGreaterThan(0.0, $wait);
    }

    public function testReserve_AfterTimePasses_RefillsTokens(): void
    {
        $bucket = $this->bucket(burst: 2.0, refillPerSecond: 1.0);

        $bucket->reserve('search');
        $bucket->reserve('search');
        self::assertNotNull($bucket->reserve('search'));

        $this->clock->advance(1);

        self::assertNull($bucket->reserve('search'), 'One second at 1/s refills exactly one token.');
    }

    public function testReserve_AfterLongIdlePeriod_CapsRefillAtBurstSize(): void
    {
        $bucket = $this->bucket(burst: 2.0, refillPerSecond: 1.0);

        $bucket->reserve('search');
        $this->clock->advance(3600);

        // An hour of refill must not let a caller spend more than the burst.
        self::assertNull($bucket->reserve('search'));
        self::assertNull($bucket->reserve('search'));
        self::assertNotNull($bucket->reserve('search'));
    }

    public function testReserve_AfterWaitingTheReportedTime_Succeeds(): void
    {
        $bucket = $this->bucket(burst: 1.0, refillPerSecond: 0.5);

        self::assertNull($bucket->reserve('search'));

        $wait = $bucket->reserve('search');
        self::assertNotNull($wait);

        $this->clock->advance((int) ceil($wait));

        self::assertNull($bucket->reserve('search'), 'Waiting the reported time must be enough.');
    }

    public function testReserve_ForDifferentOperations_KeepsBucketsSeparate(): void
    {
        $bucket = $this->bucket(burst: 1.0);

        self::assertNull($bucket->reserve('search'));
        self::assertNotNull($bucket->reserve('search'));

        self::assertNull($bucket->reserve('submit_batch'), 'A separate operation has its own allowance.');
    }

    public function testReserve_FromTwoProcesses_SharesOneStoredBucket(): void
    {
        // Stands in for the API and worker containers: two processes, one quota.
        $api = $this->bucket(burst: 2.0);
        $worker = $this->bucket(burst: 2.0);

        self::assertNull($api->reserve('search'));
        self::assertNull($worker->reserve('search'));
        self::assertNotNull($api->reserve('search'), 'The second process must see the first one spending.');
    }
}
