<?php

declare(strict_types=1);

namespace Catalog\Tests\Integration;

use Catalog\Support\Config;
use Catalog\Support\Database;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

/**
 * Base for tests that need a real MySQL. The suite is skipped when no database
 * is configured, so a contributor can run the unit suite with nothing installed,
 * while CI always provides one and therefore always runs these.
 */
abstract class DatabaseTestCase extends TestCase
{
    protected Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $host = getenv('DB_HOST');
        if ($host === false || $host === '') {
            self::markTestSkipped('No DB_HOST set; start the database or run the unit suite.');
        }

        $config = Config::fromEnvironment([
            'DB_HOST' => $host,
            'DB_PORT' => getenv('DB_PORT') ?: '3306',
            'DB_NAME' => getenv('DB_NAME') ?: 'catalog',
            'DB_USER' => getenv('DB_USER') ?: 'catalog',
            'DB_PASSWORD' => getenv('DB_PASSWORD') ?: 'catalog',
        ]);

        $this->connection = Database::connect($config);

        if (!Database::isReachable($this->connection)) {
            self::markTestSkipped('Database is configured but not reachable.');
        }

        // Each test starts from a known table. Migrations have already run.
        $this->connection->executeStatement('DELETE FROM products');
    }
}
