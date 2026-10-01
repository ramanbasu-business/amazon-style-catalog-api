<?php

declare(strict_types=1);

namespace Catalog\Tests\Unit\Support;

use Catalog\Support\Config;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ConfigTest extends TestCase
{
    public function testString_WhenSet_ReturnsValue(): void
    {
        $config = new Config(['DB_HOST' => 'db']);

        self::assertSame('db', $config->string('DB_HOST'));
    }

    public function testString_WhenKeyAbsent_ReturnsDefault(): void
    {
        $config = new Config([]);

        self::assertSame('local', $config->string('APP_ENV', 'local'));
        self::assertSame(3306, $config->int('DB_PORT', 3306));
    }

    public function testString_WhenValueEmpty_TreatsAsAbsent(): void
    {
        // An unset variable in a .env file arrives as "", which must not be
        // mistaken for a deliberate empty password or a zero.
        $config = new Config(['APP_ENV' => '', 'DB_PORT' => '']);

        self::assertSame('local', $config->string('APP_ENV', 'local'));
        self::assertSame(3306, $config->int('DB_PORT', 3306));
    }

    public function testString_WhenRequiredValueMissing_Throws(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing required configuration value: API_KEY');

        (new Config([]))->string('API_KEY');
    }

    public function testInt_WhenValueNotNumeric_Throws(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must be an integer');

        (new Config(['DB_PORT' => '3306x']))->int('DB_PORT');
    }

    public function testBool_WithCommonSpellings_ParsesCorrectly(): void
    {
        $config = new Config(['A' => 'true', 'B' => '1', 'C' => 'yes', 'D' => 'false', 'E' => 'off']);

        self::assertTrue($config->bool('A'));
        self::assertTrue($config->bool('B'));
        self::assertTrue($config->bool('C'));
        self::assertFalse($config->bool('D'));
        self::assertFalse($config->bool('E'));
        self::assertTrue($config->bool('MISSING', true));
    }

    public function testFromEnvironment_WithNonStringEntries_IgnoresThem(): void
    {
        $config = Config::fromEnvironment(['API_KEY' => 'k', 'argv' => ['a', 'b']]);

        self::assertSame('k', $config->string('API_KEY'));
        self::assertSame('fallback', $config->string('argv', 'fallback'));
    }
}
